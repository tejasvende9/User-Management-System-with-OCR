<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .preview-container {
            max-width: 100%;
            margin-top: 20px;
        }
        .preview-data {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        #userForm {
            display: none;
        }
        .form-label {
            font-weight: 500;
        }
        .required::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        .badge {
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">User Management System</h2>
        
        <!-- Create User Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Create New User</h5>
            </div>
            <div class="card-body">
                <!-- Document Upload Section -->
                <div id="uploadSection">
                    <div class="mb-3">
                        <label for="document" class="form-label required">Upload Government Document</label>
                        <input type="file" class="form-control" id="document" name="document" accept="image/*,application/pdf" required>
                        <div class="alert alert-info mt-2">
                            <i class="fas fa-info-circle"></i> For better results:
                            <ul class="mb-0 mt-1">
                                <li>Upload a clear, well-lit document</li>
                                <li>Make sure text is not blurry</li>
                                <li>Avoid shadows and reflections</li>
                                <li>Keep document straight and properly aligned</li>
                            </ul>
                        </div>
                    </div>
                    <button type="button" id="uploadBtn" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i> Upload & Extract Data
                    </button>
                    <div id="processingMessage" class="alert alert-warning d-none mt-3">
                        <i class="fas fa-sync fa-spin"></i> Processing document... Please ensure your document is clear and properly oriented for best results.
                    </div>
                </div>

                <!-- User Form (Hidden initially) -->
                <form id="userForm" class="mt-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label required">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="document_number" class="form-label required">Document Number</label>
                            <input type="text" class="form-control" id="document_number" name="document_number" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_of_birth" class="form-label required">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="address" class="form-label required">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="1" required></textarea>
                        </div>
                    </div>
                    <div class="preview-container">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-image me-1"></i> Document Preview:</h6>
                                <img id="documentPreview" src="" alt="Document Preview" style="max-width: 100%; max-height: 200px;">
                            </div>
                            <div class="col-md-6">
                                <div id="extractedText" class="preview-data"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" id="editBtn" class="btn btn-warning">
                            <i class="fas fa-edit me-1"></i> Edit Information
                        </button>
                        <button type="submit" id="saveBtn" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Save User
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-1"></i> User List</h5>
            </div>
            <div class="card-body">
                <table id="usersTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Document Number</th>
                            <th>Date of Birth</th>
                            <th>Address</th>
                            <th>Document Image</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-1"></i> Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" id="editUserId">
                        <div class="mb-3">
                            <label for="editFullName" class="form-label required">Full Name</label>
                            <input type="text" class="form-control" id="editFullName" required>
                        </div>
                        <div class="mb-3">
                            <label for="editDocumentNumber" class="form-label required">Document Number</label>
                            <input type="text" class="form-control" id="editDocumentNumber" required>
                        </div>
                        <div class="mb-3">
                            <label for="editDateOfBirth" class="form-label required">Date of Birth</label>
                            <input type="date" class="form-control" id="editDateOfBirth" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAddress" class="form-label required">Address</label>
                            <textarea class="form-control" id="editAddress" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editDocument" class="form-label">Update Document</label>
                            <input type="file" class="form-control" id="editDocument" name="document" accept="image/*,application/pdf">
                            <div class="form-text">Leave empty to keep the current document</div>
                            <div class="mt-2">
                                <img id="editDocumentPreview" src="" alt="Current Document" style="max-width: 100%; max-height: 200px; display: none;">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                    <button type="button" class="btn btn-primary" id="saveEditBtn">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
