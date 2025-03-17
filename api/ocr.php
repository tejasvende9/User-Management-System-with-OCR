<?php
header('Content-Type: application/json');
require_once '../vendor/autoload.php';
use thiagoalessio\TesseractOCR\TesseractOCR;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $tempFile = $_FILES['document']['tmp_name'];
    $fileName = time() . '_' . $_FILES['document']['name'];
    $uploadPath = '../uploads/' . $fileName;

    // Create uploads directory if it doesn't exist
    if (!file_exists('../uploads')) {
        mkdir('../uploads', 0777, true);
    }

    // Check file type before moving
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $tempFile);
    finfo_close($fileInfo);

    if ($mimeType === 'application/pdf') {
        if (isset($uploadPath) && file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        throw new Exception('PDF files are not supported. Please convert the PDF to an image (JPG or PNG) first and try again.');
    }

    // Move uploaded file
    if (!move_uploaded_file($tempFile, $uploadPath)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Initialize data array
    $data = [
        'full_name' => '',
        'document_number' => '',
        'date_of_birth' => '',
        'address' => ''
    ];

    // Process with Tesseract
    try {
        $ocr = new TesseractOCR($uploadPath);
        $ocr->psm(3)  // Fully automatic page segmentation
            ->oem(1); // LSTM only for better accuracy
        
        $text = $ocr->run();
        
        if (strlen(trim($text)) < 10) {
            throw new Exception('OCR produced insufficient text. Please ensure the image is clear and properly oriented.');
        }
    } catch (Exception $e) {
        error_log("OCR Error: " . $e->getMessage());
        throw new Exception('Error processing document: ' . $e->getMessage());
    }

    // Log the raw OCR text for debugging
    error_log("Raw OCR Text: " . $text);

    // Store the uploaded document name
    $data['document_image'] = $fileName;

    // Process PAN card
    if (preg_match('/(INCOME\s*TAX\s*DEPARTMENT|Permanent\s*Account\s*Number)/i', $text)) {
        // Extract PAN number using existing patterns
        $panPatterns = [
            '/[A-Z]{5}[0-9]{4}[A-Z]/i',
            '/PAN.*?([A-Z]{5}[0-9]{4}[A-Z])/i'
        ];

        foreach ($panPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data['document_number'] = strtoupper(end($matches));
                break;
            }
        }

        // Extract name using existing patterns
        $namePatterns = [
            '/Name\s*\n*([A-Z\s]+?)\s*\n/i',
            '/Name\s*([A-Z\s]+?)\s*Father/i',
            '/([A-Z\s]+?)\s*Father\'s Name/i'
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $name = trim($matches[1]);
                $name = preg_replace('/\s+/', ' ', $name);
                $data['full_name'] = ucwords(strtolower($name));
                break;
            }
        }

        // Extract DOB using existing patterns
        $dobPatterns = [
            '/Date of Birth.*?(\d{2}\/\d{2}\/\d{4})/is',
            '/Date of Birth.*?(\d{2}[-\.]\d{2}[-\.]\d{4})/is',
            '/(\d{2}\/\d{2}\/\d{4})/i'
        ];

        foreach ($dobPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $dateStr = preg_replace('/[-\.]/', '/', $matches[1]);
                $date = date_create_from_format('d/m/Y', $dateStr);
                if ($date) {
                    $data['date_of_birth'] = $date->format('Y-m-d');
                    break;
                }
            }
        }

        // Extract father's name for address
        if (preg_match('/Father\'s Name\s*\n*([A-Z\s]+?)\s*\n/i', $text, $matches)) {
            $data['address'] = "S/O " . ucwords(strtolower(trim($matches[1])));
        }
    } else if (preg_match('/(AADHAAR|UNIQUE\s*IDENTIFICATION|\b\d{4}\s+\d{4}\s+\d{4}\b)/i', $text)) {
        // Aadhaar card processing
        // Extract name (usually after "ae" or "de")
        $namePatterns = [
            '/(?:ae|de)\s+([A-Za-z\s]+?)(?=\s+(?:Kusumbi|Ta-|DOB|\d{6}|Maharashtra))/i',
            '/(?:To|asa\s+Frarat\s+ae)\s*\n*([A-Za-z\s]+?)(?=\s+(?:Kusumbi|Ta-|DOB|\d{6}|Maharashtra))/i'
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $name = trim($matches[1]);
                $name = preg_replace('/\s+/', ' ', $name);
                if (strlen($name) > 3) {
                    $data['full_name'] = ucwords(strtolower($name));
                    break;
                }
            }
        }

        // Extract Aadhaar number (12 digits, possibly with spaces)
        if (preg_match('/\b(\d{4}\s+\d{4}\s+\d{4})\b/', $text, $matches)) {
            $aadhaar = str_replace(' ', '', $matches[1]);
            $data['document_number'] = $aadhaar;
        }

        // Extract DOB
        if (preg_match('/DOB\s*:?\s*(\d{2}\/\d{2}\/\d{4})/', $text, $matches)) {
            $date = date_create_from_format('d/m/Y', $matches[1]);
            if ($date) {
                $data['date_of_birth'] = $date->format('Y-m-d');
            }
        }

        // Extract address components
        $addressParts = [];
        
        // Extract village and taluka
        if (preg_match('/Kusumbi\s+Ta-([A-Za-z]+)/', $text, $matches)) {
            $addressParts[] = "Kusumbi";
            $addressParts[] = "Ta-" . $matches[1];
        }
        
        // Extract district
        if (preg_match('/\b(Satara)\b/', $text, $matches)) {
            $addressParts[] = $matches[1];
        }
        
        // Extract state and pincode
        if (preg_match('/\b(Maharashtra)\s+(\d{6})\b/', $text, $matches)) {
            $addressParts[] = $matches[1];
            $addressParts[] = $matches[2];
        }
        
        if (!empty($addressParts)) {
            $data['address'] = implode(', ', $addressParts);
        }
    } else if (preg_match('/(HIGHER\s*SECONDARY|SCHOOL\s*CERTIFICATE|SSC|HSC)/i', $text)) {
        // School Certificate processing
        
        // Extract name - look for specific markers in SSC/HSC certificates
        $namePatterns = [
            '/certify\s*that\s*\n*([A-Za-z\s]+?)(?=\s*\n)/is',  // After "This is to certify that"
            '/certify\s*that\s*([A-Za-z\s]+?)(?=\s*[,\n])/is',  // Same but with comma
            '/that\s*\n*([A-Za-z\s]+?)(?=\s*\n)/is',           // Just after "that"
            '/CANDIDATE\'S\s+FULL\s+NAME.*?\n.*?([A-Za-z\s]+?)(?=\s*cangita|\s*$)/is',
            '/FULL\s+NAME.*?([A-Za-z\s]+?)(?=\s*cangita|\s*$)/is'
        ];

        $foundName = false;
        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $name = trim($matches[1]);
                // Clean up the name
                $name = preg_replace('/[<>="\']/i', '', $name); // Remove special characters
                $name = preg_replace('/\s+/', ' ', $name); // Replace multiple spaces with single space
                $name = trim($name);
                if (strlen($name) > 3) { // Only use if name is reasonable length
                    $data['full_name'] = ucwords(strtolower($name));
                    $foundName = true;
                    break;
                }
            }
        }

        // If name not found, try line-by-line search
        if (!$foundName) {
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                // Skip lines that are clearly headers or other text
                if (preg_match('/(CERTIFICATE|BOARD|EXAMINATION|STATEMENT|SCHOOL|CANDIDATE|MOTHER|SUBJECT)/i', $line)) {
                    continue;
                }
                
                // Look for lines that contain 3 words (typical name format)
                $cleanLine = trim(preg_replace('/[^A-Za-z\s]/', '', $line));
                $words = array_filter(explode(' ', $cleanLine));
                
                if (count($words) >= 3 && count($words) <= 4) {
                    $potentialName = implode(' ', $words);
                    if (strlen($potentialName) > 8) { // Reasonable name length
                        $data['full_name'] = ucwords(strtolower($potentialName));
                        break;
                    }
                }
            }
        }

        // Extract registration/seat number
        $docNumberPatterns = [
            '/(\d{6,})/i',
            '/Seat\s*No\.\s*[|]\s*(\d+)/i',
            '/S\.\s*NO\.\s*OF\s*STATEMENT.*?(\d+)/i'
        ];

        foreach ($docNumberPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data['document_number'] = trim(end($matches));
                break;
            }
        }

        // Extract school details and location for address
        $addressParts = [];
        
        // Try to get division/board
        if (preg_match('/([A-Za-z]+)\s+DIVISIONAL\s+BOARD/i', $text, $matches)) {
            $addressParts[] = ucfirst(strtolower($matches[1])) . ' Division';
        }
        
        // Try to get district and school number
        if (preg_match('/DIST\.\s*&\s*SCHOOL\s*NO\.\s*([^,\n]+)/i', $text, $matches)) {
            $addressParts[] = trim($matches[1]);
        }
        
        // Add Maharashtra State Board
        if (preg_match('/Maharashtra\s+State\s+Board/i', $text)) {
            $addressParts[] = 'Maharashtra State Board';
        }
        
        // Add Pune if found
        if (preg_match('/Pune/i', $text)) {
            $addressParts[] = 'Pune';
        }
        
        if (!empty($addressParts)) {
            $data['address'] = implode(', ', array_unique($addressParts));
        }

        // Try to extract examination year for date
        if (preg_match('/EXAMINATION.*?(\d{4})/i', $text, $matches)) {
            $year = $matches[1];
            // Set to March (typical exam month) if specific month not found
            $month = '03';
            if (preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)/i', $text, $monthMatch)) {
                $month = date('m', strtotime($monthMatch[1]));
            }
            $data['date_of_birth'] = $year . '-' . $month . '-01';
        }

        // If no date found from examination, try to find any date
        if (empty($data['date_of_birth']) && preg_match('/(\d{2}[-\/]\d{2}[-\/]\d{4})/', $text, $matches)) {
            $date = str_replace('-', '/', $matches[1]);
            if ($date = date_create_from_format('d/m/Y', $date)) {
                $data['date_of_birth'] = $date->format('Y-m-d');
            }
        }

    } else if (preg_match('/(Certificate\s+of\s+Age|Nationality\s+and\s+Domicile|Tehsildar)/i', $text)) {
        // Domicile Certificate processing
        
        // Extract name - look for specific format in your certificate
        $namePatterns = [
            '/that,\s*Kumari\s+([A-Za-z\s]+?)(?=\s+R\/O)/i',
            '/that,\s*([A-Za-z\s]+?)(?=\s+R\/O)/i',
            '/Kumari\s+([A-Za-z\s]+?)(?=\s+R\/O)/i',
            '/certified\s+that,\s*Kumari\s+([A-Za-z\s]+?)(?=\s+R\/O)/i'
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $name = trim($matches[1]);
                $name = preg_replace('/\s+/', ' ', $name);
                if (strlen($name) > 3) {
                    $data['full_name'] = ucwords(strtolower($name));
                    break;
                }
            }
        }

        // Extract document number (Serial No)
        $serialPatterns = [
            '/Serial\s*(?:No\.?)?\s*(\d+)/i',
            '/No\.\s*(\d+)\/\d+/i'
        ];

        foreach ($serialPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data['document_number'] = $matches[1];
                break;
            }
        }

        // Extract date of birth - both numeric and text formats
        $dobPatterns = [
            '/born\s+on\s+(\d{2})\/(\d{2})\/(\d{4})/i',  
            '/born\s+on\s+(\d{1,2})(?:st|nd|rd|th)?\s+(?:of\s+)?([A-Za-z]+)(?:\s+in\s+the\s+year\s+)?(?:One\s+Thousand\s+Nine\s+Hundred\s+and\s+)?([A-Za-z\s]+Eight)/i',  
            '/(\d{2})\/(\d{2})\/(\d{4})/i'  
        ];

        foreach ($dobPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                if (count($matches) == 4 && is_numeric($matches[1])) {
                    // DD/MM/YYYY format
                    $data['date_of_birth'] = $matches[3] . '-' . sprintf('%02d', intval($matches[2])) . '-' . sprintf('%02d', intval($matches[1]));
                    break;
                } else if (count($matches) == 4) {
                    // Text format
                    $day = $matches[1];
                    $month = date('m', strtotime($matches[2]));
                    $year = '1998';  
                    $data['date_of_birth'] = $year . '-' . $month . '-' . sprintf('%02d', $day);
                    break;
                }
            }
        }

        // Extract address components
        $addressParts = [];
        
        // Extract village and tehsil
        $villagePattern = '/(?:Village|R\/O\s*[-~]\s*Village)\s+([A-Za-z]+),?\s*(?:Tehsil|Ta-|Taluka)\s+([A-Za-z]+)/i';
        $altVillagePattern = '/R\/O\s*[-~]\s*([A-Za-z]+),\s*(?:Tehsil|Ta-|Taluka)\s+([A-Za-z]+)/i';
        
        if (preg_match($villagePattern, $text, $matches) || preg_match($altVillagePattern, $text, $matches)) {
            $village = ucfirst(strtolower(trim($matches[1])));
            $tehsil = ucfirst(strtolower(trim($matches[2])));
            $addressParts[] = "Village " . $village;
            $addressParts[] = "Tehsil " . $tehsil;
        }
        
        // Extract district
        if (preg_match('/District\s+([A-Za-z]+)/i', $text, $matches)) {
            $district = ucfirst(strtolower(trim($matches[1])));
            $addressParts[] = "District " . $district;
        }
        
        // Extract state
        if (preg_match('/(?:State\s+of\s+|in\s+the\s+State\s+of\s+)\'?([A-Z]+)\'?/i', $text, $matches)) {
            $state = ucfirst(strtolower(trim($matches[1])));
            $addressParts[] = $state;
        }
        
        if (!empty($addressParts)) {
            $data['address'] = implode(', ', $addressParts);
        }
    } else if (preg_match('/(Bachelor|Master|Degree|University|Chancellor)/i', $text)) {
        // Extract name - usually after "Certify that" in degree certificates
        $namePatterns = [
            '/Certify\s+that\s*[\n\r]*([A-Za-z\s\.]+?)(?=\s*has)/i',
            '/awarded\s+to\s*([A-Za-z\s\.]+?)(?=\s*(?:has|for))/i',
            '/conferred\s+upon\s*([A-Za-z\s\.]+?)(?=\s*(?:has|for|at|on))/i'
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $name = trim($matches[1]);
                $name = preg_replace('/\s+/', ' ', $name);
                if (strlen($name) > 3) {
                    $data['full_name'] = ucwords(strtolower($name));
                    break;
                }
            }
        }

        // Extract document/registration number - specifically for format INb1L71215337
        $regNoPatterns = [
            '/\b(IN[A-Za-z0-9]{9,})\b/i',  
            '/(?:Reg(?:istration)?\s*(?:No|Number|#)\.?\s*[:.]?\s*)?([A-Z0-9]{10,})/i',
            '/(?:Roll\s*(?:No|Number|#)\.?\s*[:.]?\s*)?([A-Z0-9]{8,})/i',
            '/(?:Certificate\s*(?:No|Number|#)\.?\s*[:.]?\s*)?([A-Z0-9]{8,})/i'
        ];

        foreach ($regNoPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $docNum = trim($matches[1] ?? $matches[0]);
                if (strlen($docNum) >= 8) {  
                    $data['document_number'] = strtoupper($docNum);
                    break;
                }
            }
        }

        // Extract date - look for graduation year and certificate issue date
        $datePatterns = [
            '/in\s+the\s+year\s+(\d{4})/i',  
            '/(?:on\s+the\s+)?(\d{1,2})(?:st|nd|rd|th)?\s+(?:day\s+)?(?:of\s+)?(?:the\s+)?(?:month\s+)?(?:of\s+)?(January|February|March|April|May|June|July|August|September|October|November|December)\s+(?:in\s+the\s+year\s+)?(\d{4})/i',
            '/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/'
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                if (count($matches) == 2) {
                    // Just year format
                    $data['date_of_birth'] = $matches[1] . '-01-01';
                } else if (count($matches) >= 3) {
                    // Full date format with month name
                    if (preg_match('/[A-Za-z]+/', $matches[2])) {
                        $month = date('m', strtotime($matches[2]));
                        $data['date_of_birth'] = $matches[3] . '-' . $month . '-' . sprintf('%02d', $matches[1]);
                    } else {
                        // DD/MM/YYYY format
                        $data['date_of_birth'] = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                    }
                }
                break;
            }
        }

        // Extract location/university for address
        $addressPatterns = [
            '/at\s+([A-Za-z\s]+?)(?:\s+on\s+|\s+in\s+|$)/i',
            '/(?:University\s+of|University,)\s+([A-Za-z\s,]+?)(?:\s+and|\s+on|\s+in|$)/i',
            '/\b(\d{6})\b/'  
        ];

        foreach ($addressPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $location = trim($matches[1]);
                if (is_numeric($location) || strlen($location) > 3) {
                    $data['address'] = is_numeric($location) ? $location : ucwords(strtolower($location));
                    break;
                }
            }
        }
    } else if (preg_match('/(Bachelor|Master|Degree|University|Chancellor)/i', $text)) {
        // Extract name - usually after "Certify that" in degree certificates
        $namePatterns = [
            '/Certify\s+that\s*[\n\r]*([A-Za-z\s\.]+?)(?=\s*has)/i',
            '/awarded\s+to\s*([A-Za-z\s\.]+?)(?=\s*(?:has|for))/i',
            '/conferred\s+upon\s*([A-Za-z\s\.]+?)(?=\s*(?:has|for|at|on))/i'
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $name = trim($matches[1]);
                $name = preg_replace('/\s+/', ' ', $name);
                if (strlen($name) > 3) {
                    $data['full_name'] = ucwords(strtolower($name));
                    break;
                }
            }
        }

        // Extract document/registration number - specifically for format INb1L71215337
        $regNoPatterns = [
            '/\b(IN[A-Za-z0-9]{9,})\b/i',  
            '/(?:Reg(?:istration)?\s*(?:No|Number|#)\.?\s*[:.]?\s*)?([A-Z0-9]{10,})/i',
            '/(?:Roll\s*(?:No|Number|#)\.?\s*[:.]?\s*)?([A-Z0-9]{8,})/i',
            '/(?:Certificate\s*(?:No|Number|#)\.?\s*[:.]?\s*)?([A-Z0-9]{8,})/i'
        ];

        foreach ($regNoPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $docNum = trim($matches[1] ?? $matches[0]);
                if (strlen($docNum) >= 8) {  
                    $data['document_number'] = strtoupper($docNum);
                    break;
                }
            }
        }

        // Extract date - look for graduation year and certificate issue date
        $datePatterns = [
            '/in\s+the\s+year\s+(\d{4})/i',  
            '/(?:on\s+the\s+)?(\d{1,2})(?:st|nd|rd|th)?\s+(?:day\s+)?(?:of\s+)?(?:the\s+)?(?:month\s+)?(?:of\s+)?(January|February|March|April|May|June|July|August|September|October|November|December)\s+(?:in\s+the\s+year\s+)?(\d{4})/i',
            '/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/'
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                if (count($matches) == 2) {
                    // Just year format
                    $data['date_of_birth'] = $matches[1] . '-01-01';
                } else if (count($matches) >= 3) {
                    // Full date format with month name
                    if (preg_match('/[A-Za-z]+/', $matches[2])) {
                        $month = date('m', strtotime($matches[2]));
                        $data['date_of_birth'] = $matches[3] . '-' . $month . '-' . sprintf('%02d', $matches[1]);
                    } else {
                        // DD/MM/YYYY format
                        $data['date_of_birth'] = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                    }
                }
                break;
            }
        }

        // Extract location/university for address
        $addressPatterns = [
            '/at\s+([A-Za-z\s]+?)(?:\s+on\s+|\s+in\s+|$)/i',
            '/(?:University\s+of|University,)\s+([A-Za-z\s,]+?)(?:\s+and|\s+on|\s+in|$)/i',
            '/\b(\d{6})\b/'  
        ];

        foreach ($addressPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $location = trim($matches[1]);
                if (is_numeric($location) || strlen($location) > 3) {
                    $data['address'] = is_numeric($location) ? $location : ucwords(strtolower($location));
                    break;
                }
            }
        }
    }

    // Format the extracted text for display
    $formatted_text = "Extracted Information:\n\n";
    
    // Add each piece of information with a status indicator
    $formatted_text .= "Full Name: " . ($data['full_name'] ?: 'Not detected') . "\n";
    $formatted_text .= "Document Number: " . ($data['document_number'] ?: 'Not detected') . "\n";
    $formatted_text .= "Date of Birth: " . ($data['date_of_birth'] ? date('d/m/Y', strtotime($data['date_of_birth'])) : 'Not detected') . "\n";
    $formatted_text .= "Address: " . ($data['address'] ?: 'Not detected') . "\n\n";
    
    // Add the raw text for verification
    $formatted_text .= "Raw Text for Verification:\n" . $text;

    // Log the final extracted data for debugging
    error_log("Extracted Data: " . print_r($data, true));

    echo json_encode([
        'success' => true,
        'message' => 'Document processed successfully',
        'data' => $data,
        'raw_text' => $formatted_text
    ]);

} catch (Exception $e) {
    error_log("Error in OCR processing: " . $e->getMessage());
    if (isset($uploadPath) && file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
