# User Management System with OCR

A web application that allows you to create, view, edit, and delete users using OCR technology to extract information from government documents.

## Requirements

1. XAMPP (or similar web server with PHP and MySQL)
2. PHP 7.4 or higher
3. MySQL 5.7 or higher
4. Composer
5. Tesseract OCR

## Installation

1. Install Tesseract OCR:
   - Download and install Tesseract OCR from: https://github.com/UB-Mannheim/tesseract/wiki
   - Add Tesseract to your system PATH

2. Set up the database:
   ```sql
   CREATE DATABASE task_db;
   USE task_db;

   CREATE TABLE users (
       id INT AUTO_INCREMENT PRIMARY KEY,
       full_name VARCHAR(255) NOT NULL,
       document_number VARCHAR(50) NOT NULL,
       date_of_birth DATE NOT NULL,
       address TEXT NOT NULL,
       document_image VARCHAR(255) NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```

3. Install PHP dependencies:
   ```bash
   composer install
   ```

4. Create required directories:
   ```bash
   mkdir uploads
   ```

5. Configure your web server:
   - Make sure the project is in your web server's document root
   - Enable PHP extensions: pdo_mysql, gd

## Usage

1. Access the application through your web browser: `http://localhost/Task1`
2. Upload a government document image
3. Click "Upload & Extract Data" to perform OCR
4. Review the extracted data
5. Click "Create User" to save the information
6. Use the data table to view, edit, or delete users

## Features

- OCR-based data extraction from government documents
- Preview extracted data before saving
- CRUD operations for user management
- DataTables integration for sorting and searching
- Responsive design using Bootstrap 5

## Security Considerations

- Input validation and sanitization
- PDO prepared statements for database operations
- File type validation for uploads
- Secure file storage outside web root (recommended)

## Notes

- The OCR accuracy depends on the quality of the uploaded document
- Supported image formats: PNG, JPEG, TIFF
- Maximum file size is determined by your PHP configuration
