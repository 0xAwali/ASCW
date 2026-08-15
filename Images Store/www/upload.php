<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$upload_message = '';
$upload_success = false;
$message_type = '';
$uploaded_file_path = '';

$upload_dir = __DIR__ . '/uploadfiles/';
$upload_path_display = 'uploadfiles/';

function generateCSRFToken() {
    return bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token'] = generateCSRFToken();
    $_SESSION['csrf_token_used'] = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || 
        !isset($_SESSION['csrf_token']) || 
        $_SESSION['csrf_token_used'] === true ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['csrf_token'] = generateCSRFToken();
        $_SESSION['csrf_token_used'] = false;
        $upload_message = 'Security token validation failed. Please refresh and try again.';
        $message_type = 'error';
        goto display_page;
    }
    $_SESSION['csrf_token_used'] = true;
    if (empty($_FILES['file_upload'])) {
        $upload_message = 'No file uploaded.';
        $message_type = 'error';
        goto display_page;
    }
    if ($_FILES['file_upload']['error'] !== UPLOAD_ERR_OK) {
        $upload_message = 'An error occurred when uploading. Error code: ' . $_FILES['file_upload']['error'];
        $message_type = 'error';
        goto display_page;
    }

    if (!is_uploaded_file($_FILES['file_upload']['tmp_name'])) {
        $upload_message = 'Invalid file upload.';
        $message_type = 'error';
        goto display_page;
    }

    if ($_FILES['file_upload']['size'] > 500000) {
        $upload_message = 'File uploaded exceeds maximum upload size (500 KB).';
        $message_type = 'error';
        goto display_page;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actual_mime = finfo_file($finfo, $_FILES['file_upload']['tmp_name']);
    finfo_close($finfo);
    $allowed_mimes = ['image/jpeg', 'image/png'];
    if (!in_array($actual_mime, $allowed_mimes)) {
        $upload_message = 'Unsupported filetype uploaded. Only JPEG and PNG allowed.';
        $message_type = 'error';
        goto display_page;
    }
    $image_info = getimagesize($_FILES['file_upload']['tmp_name']);
    if ($image_info === false) {
        $upload_message = 'Invalid image file.';
        $message_type = 'error';
        goto display_page;
    }
    $original_name = basename($_FILES['file_upload']['name']);
    $original_name = preg_replace('/[^a-zA-Z0-9._-]/', '', $original_name);
    $ext_map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];
    $ext = $ext_map[$actual_mime];
    $allowed_exts = ['jpg', 'jpeg', 'png'];
    $input_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (!in_array($input_ext, $allowed_exts) || $input_ext !== $ext) {
        $upload_message = 'Invalid file extension.';
        $message_type = 'error';
        goto display_page;
    }

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $new_filename = bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    $new_path = $upload_dir . $new_filename;

    if (!move_uploaded_file($_FILES['file_upload']['tmp_name'], $new_path)) {
        $upload_message = 'Error uploading file - check destination is writeable.';
        $message_type = 'error';
        goto display_page;
    }

    $upload_message = 'File uploaded successfully!';
    $uploaded_file_path = $upload_path_display . $new_filename;
    $upload_success = true;
    $message_type = 'success';
    $_SESSION['csrf_token'] = generateCSRFToken();
    $_SESSION['csrf_token_used'] = false;
}

display_page:
if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token_used'] === true) {
    $_SESSION['csrf_token'] = generateCSRFToken();
    $_SESSION['csrf_token_used'] = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Images Store</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 0;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            margin: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #f8f9ff;
            margin-bottom: 25px;
        }

        .upload-area:hover {
            border-color: #764ba2;
            background-color: #f0f2ff;
        }

        .upload-area.dragover {
            border-color: #764ba2;
            background-color: #e8ebff;
        }

        .upload-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .upload-area p {
            color: #666;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .upload-area span {
            color: #667eea;
            font-weight: 600;
            font-size: 14px;
        }

        #file_upload {
            display: none;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .file-name {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
            padding: 10px;
            background: #f0f2ff;
            border-radius: 5px;
            display: none;
        }

        .file-name.show {
            display: block;
        }

        .file-name strong {
            color: #667eea;
        }

        .submit-btn {
            width: 100%;
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .logout-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 14px;
            backdrop-filter: blur(10px);
            z-index: 1000;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            margin-top: auto;
        }

        footer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 40px 20px;
            width: 100%;
        }

        footer h1 {
            font-size: 32px;
            font-weight: 700;
            text-transform: capitalize;
            letter-spacing: 1px;
        }

        footer h1 span {
            color: #ffd700;
        }

        .message-box {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: none;
            animation: slideIn 0.3s ease-out;
        }

        .message-box.show {
            display: block;
        }

        .message-box.success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }

        .message-box.error {
            background: #f8d7da;
            border: 2px solid #f5453d;
            color: #721c24;
        }

        .message-box p {
            margin: 0;
            font-weight: 600;
            font-size: 16px;
            line-height: 1.6;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .upload-success-box {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            background: #d4edda;
            border-radius: 8px;
            border: 2px solid #28a745;
            display: <?php echo ($upload_success && !empty($uploaded_file_path)) ? 'block' : 'none'; ?>;
            animation: slideIn 0.3s ease-out;
        }

        .upload-success-box .success-title {
            color: #155724;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .upload-success-box code {
            background: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #667eea;
            display: block;
            word-break: break-all;
            border: 1px solid #c3e6cb;
        }

        .upload-success-box img {
            margin-top: 15px;
            max-width: 100%;
            max-height: 200px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
        }

        .security-badge {
            text-align: center;
            margin-top: 15px;
            font-size: 12px;
            color: #999;
        }

        .user-info {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9ff;
            border-radius: 5px;
        }

        .user-info strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>

    <footer>
        <h1>👋 Welcome to Our <span>Images Store</span></h1>
    </footer>

    <div class="container">
        <?php if ($upload_message): ?>
        <div class="message-box <?php echo $message_type; ?> show">
            <p><?php echo htmlspecialchars($upload_message); ?></p>
        </div>
        <?php endif; ?>

        <div class="header">
            <h1>📸 Upload Image</h1>
            <p>Choose a beautiful image to upload and save in our store</p>
        </div>

        <form method="post" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <div class="upload-area" id="uploadArea">
                    <div class="upload-icon">📁</div>
                    <p>Drag and drop your image here</p>
                    <span>or click to browse</span>
                </div>
                <input type="file" id="file_upload" name="file_upload" accept="image/png,image/jpeg" required>
                <div class="file-name" id="fileName"></div>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">Upload Image</button>
        </form>

        <div class="upload-success-box" id="uploadSuccessBox">
            <div class="success-title">File uploaded successfully</div>
            <code><?php echo htmlspecialchars($uploaded_file_path); ?></code>
            <?php if ($upload_success && !empty($uploaded_file_path)): ?>
            <img src="<?php echo htmlspecialchars($uploaded_file_path); ?>" alt="Uploaded image">
            <?php endif; ?>
        </div>

    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('file_upload');
        const fileName = document.getElementById('fileName');
        const uploadForm = document.getElementById('uploadForm');

        uploadArea.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', handleFileSelect);

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        function handleFileSelect() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                
                const validTypes = ['image/jpeg', 'image/png'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid JPEG or PNG image.');
                    fileInput.value = '';
                    fileName.classList.remove('show');
                    return;
                }
                
                if (file.size > 500000) {
                    alert('File size exceeds 500KB limit.');
                    fileInput.value = '';
                    fileName.classList.remove('show');
                    return;
                }
                
                const fileSize = (file.size / 1024).toFixed(2);
                fileName.innerHTML = `<strong>Selected:</strong> ${file.name} (${fileSize} KB)`;
                fileName.classList.add('show');
            } else {
                fileName.classList.remove('show');
            }
        }

        uploadForm.addEventListener('submit', (e) => {
            if (fileInput.files.length === 0) {
                e.preventDefault();
                alert('Please select an image to upload');
            }
        });
    </script>
    <header>
        &copy; <strong>Arab Security Cyber Wargames</strong> 2026
    </header>
</body>
</html>
