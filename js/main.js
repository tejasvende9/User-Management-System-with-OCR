$(document).ready(function() {
    // Initialize DataTable
    const usersTable = $('#usersTable').DataTable({
        ajax: {
            url: 'api/users/get_users.php',  // Updated API endpoint
            dataSrc: 'data'
        },
        columns: [
            { data: 'id' },
            { data: 'full_name' },
            { data: 'document_number' },
            { data: 'date_of_birth' },
            { data: 'address' },
            { 
                data: 'document_image',
                render: function(data) {
                    return `<img src="uploads/${data}" height="50" alt="Document">`;
                }
            },
            { data: 'created_at' },
            {
                data: null,
                render: function(data) {
                    return `
                        <button class="btn btn-sm btn-primary mb-1 edit-btn" data-id="${data.id}">Edit</button>
                        <button class="btn btn-sm btn-danger delete-btn" data-id="${data.id}">Delete</button>
                    `;
                }
            }
        ]
    });

    // Store uploaded document name and file
    let uploadedDocument = '';
    let documentFile = null;

    // Handle document upload and OCR
    $('#uploadBtn').click(function() {
        const fileInput = $('#document')[0];
        
        if (fileInput.files.length === 0) {
            alert('Please select a document first');
            return;
        }

        // Store the file for later use
        documentFile = fileInput.files[0];

        const formData = new FormData();
        formData.append('document', documentFile);

        // Show loading state and processing message
        $('#uploadBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
        $('#processingMessage').removeClass('d-none');

        $.ajax({
            url: 'api/ocr.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    if (data.success) {
                        // Hide processing message
                        $('#processingMessage').addClass('d-none');
                        
                        // Store uploaded document name
                        uploadedDocument = data.data.document_image;
                        
                        // Show form and populate fields
                        $('#userForm').show();
                        $('#uploadSection').hide();
                        
                        // Set form values with placeholders if empty
                        $('#full_name').val(data.data.full_name || '').attr('placeholder', 'Enter Full Name');
                        $('#document_number').val(data.data.document_number || '').attr('placeholder', 'Enter Document Number');
                        $('#date_of_birth').val(data.data.date_of_birth || '').attr('placeholder', 'YYYY-MM-DD');
                        $('#address').val(data.data.address || '').attr('placeholder', 'Enter Address');
                        
                        // Show document preview
                        if (data.data.document_image) {
                            $('#documentPreview').attr('src', 'uploads/' + data.data.document_image)
                                               .css('display', 'block');
                        }
                        
                        // Format and display extracted text
                        let formattedHtml = '<div class="card mb-4">';
                        
                        // Add header with warning if fields are empty
                        formattedHtml += `
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Extracted Information</h5>
                                    ${!data.data.full_name ? '<span class="badge bg-warning">Manual Entry Required</span>' : ''}
                                </div>
                            </div>
                            <div class="card-body">`;

                        // Display fields with status indicators
                        const fields = [
                            { label: 'Full Name', value: data.data.full_name },
                            { label: 'Document Number', value: data.data.document_number },
                            { label: 'Date of Birth', value: data.data.date_of_birth },
                            { label: 'Address', value: data.data.address }
                        ];

                        fields.forEach(field => {
                            formattedHtml += `
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">${field.label}:</div>
                                    <div class="col-md-8">
                                        ${field.value ? 
                                            `<span class="text-success">
                                                <i class="fas fa-check-circle"></i> ${field.value}
                                            </span>` : 
                                            `<span class="text-danger">
                                                <i class="fas fa-exclamation-circle"></i> Not detected - Please enter manually
                                            </span>`
                                        }
                                    </div>
                                </div>`;
                        });

                        // Add raw text in collapsible section
                        formattedHtml += `
                            <div class="mt-3">
                                <button class="btn btn-outline-secondary btn-sm" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#rawText">
                                    Show Raw Text
                                </button>
                                <div class="collapse mt-2" id="rawText">
                                    <div class="card card-body bg-light">
                                        <pre class="mb-0" style="white-space: pre-wrap;">${data.raw_text}</pre>
                                    </div>
                                </div>
                            </div>`;

                        formattedHtml += `
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> 
                                <small>Please verify and complete all required information in the form below.</small>
                            </div>
                        </div></div>`;
                        
                        $('#extractedText').html(formattedHtml);
                        
                        // Enable form editing
                        $('#editBtn').show();
                        $('form input, form textarea').prop('readonly', false);
                        
                        // Scroll to form
                        $('html, body').animate({
                            scrollTop: $("#userForm").offset().top - 50
                        }, 500);
                    } else {
                        // Hide processing message and show error
                        $('#processingMessage').addClass('d-none');
                        alert('Error processing document: ' + data.message);
                    }
                } catch (e) {
                    // Hide processing message and show error
                    $('#processingMessage').addClass('d-none');
                    console.error('Error parsing response:', e);
                    alert('Error processing the server response');
                }
                
                // Reset upload button
                $('#uploadBtn').prop('disabled', false).html('<i class="fas fa-upload me-1"></i> Upload & Extract Data');
            },
            error: function() {
                // Hide processing message and show error
                $('#processingMessage').addClass('d-none');
                alert('Error uploading document');
                $('#uploadBtn').prop('disabled', false).html('<i class="fas fa-upload me-1"></i> Upload & Extract Data');
            }
        });
    });

    // Handle edit button
    $('#editBtn').click(function() {
        // Toggle readonly state of form fields
        const fields = ['full_name', 'document_number', 'date_of_birth', 'address'];
        fields.forEach(field => {
            const elem = $('#' + field);
            elem.prop('readonly', !elem.prop('readonly'));
        });

        // Toggle button text
        const isEditing = $(this).text() === 'Edit Information';
        $(this).text(isEditing ? 'Lock Information' : 'Edit Information')
              .toggleClass('btn-warning btn-info');
    });

    // Handle form submission
    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        
        // Ensure all fields are filled
        const fields = ['full_name', 'document_number', 'date_of_birth', 'address'];
        const formData = new FormData();
        
        fields.forEach(field => {
            const value = $('#' + field).val().trim();
            if (!value) {
                alert('Please fill in all fields');
                return;
            }
            formData.append(field, value);
        });

        // Add document if available
        if (documentFile) {
            formData.append('document_image', documentFile);
        }

        // Submit to API
        $.ajax({
            url: 'api/users/create.php',  // Updated API endpoint
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('User created successfully!');
                    usersTable.ajax.reload();
                    $('#userForm')[0].reset();
                    $('#userForm').hide();
                    $('#uploadSection').show();
                    $('#documentPreview').hide();
                    $('#extractedText').empty();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error creating user');
            }
        });
    });

    // Edit User
    $('#usersTable').on('click', '.edit-btn', function() {
        const userId = $(this).data('id');
        
        // Fetch user data
        $.ajax({
            url: `api/users/read_one.php?id=${userId}`,  // Updated API endpoint
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    const user = response.data;
                    $('#editId').val(user.id);
                    $('#editFullName').val(user.full_name);
                    $('#editDocumentNumber').val(user.document_number);
                    $('#editDateOfBirth').val(user.date_of_birth);
                    $('#editAddress').val(user.address);
                    if (user.document_image) {
                        $('#currentDocumentPreview').attr('src', 'uploads/' + user.document_image).show();
                    }
                    $('#editModal').modal('show');
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    });

    // Preview new document when selected
    $('#editDocument').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#editDocumentPreview').attr('src', e.target.result)
                                       .css('display', 'block');
            };
            reader.readAsDataURL(file);
        }
    });

    // Save Edit
    $('#editForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        $.ajax({
            url: 'api/users/update.php',  // Updated API endpoint
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#editModal').modal('hide');
                    usersTable.ajax.reload();
                    alert('User updated successfully!');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error updating user');
            }
        });
    });

    // Delete User
    $('#usersTable').on('click', '.delete-btn', function() {
        if (confirm('Are you sure you want to delete this user?')) {
            const userId = $(this).data('id');
            
            $.ajax({
                url: 'api/users/delete.php',  // Updated API endpoint
                type: 'DELETE',
                data: JSON.stringify({ id: userId }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        usersTable.ajax.reload();
                        alert('User deleted successfully!');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error deleting user');
                }
            });
        }
    });
});
