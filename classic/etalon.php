<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$recipients = [
  'dolcegabbana2010@gmail.com',
];

$smtp_config = [
    'host' => 'smtp.gmail.com',
    'username' => 'dolcegabbana2010@gmail.com',
    'password' => 'laml uciy kwjc cczh',
    'port' => 587,
    'encryption' => 'tls',
];

function writeLog($type, $message, $data = null) {
    try {
        $logDir = __DIR__ . '/logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        // Проверяем права на запись
        if (!is_writable($logDir)) {
            error_log("Log directory is not writable: $logDir");
            return false;
        }
        
        $date = date('Y-m-d H:i:s');
        $logMessage = "[$date] [$type] $message\n";
        if ($data) {
            $logMessage .= "Data: " . print_r($data, true) . "\n";
        }
        $logMessage .= "------------------------------------------------\n";
        
        $filename = $type === 'ERROR' ? 'error.log' : 'success.log';
        $filePath = $logDir . '/' . $filename;
        
        if (file_put_contents($filePath, $logMessage, FILE_APPEND) === false) {
            error_log("Failed to write to log file: $filePath");
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error in writeLog function: " . $e->getMessage());
        return false;
    }
}

function sendEmail($to, $subject) {
    global $smtp_config;

    try {
        // Проверяем существование файла шаблона
        $templatePath = __DIR__ . '/tpl.html';
        if (!file_exists($templatePath)) {
            throw new Exception("Template file not found at: $templatePath");
        }

        // Загружаем HTML шаблон
        $htmlTemplate = file_get_contents($templatePath);
        if ($htmlTemplate === false) {
            throw new Exception("Failed to read template file");
        }

        $mail = new PHPMailer(true);

        // Включаем детальное логирование
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            writeLog('SMTP_DEBUG', $str);
        };

        // Настройка SMTP
        $mail->isSMTP();
        $mail->Hostname = 'aceit.group';
        $mail->Host = $smtp_config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['username'];
        $mail->Password = $smtp_config['password'];
        $mail->SMTPSecure = $smtp_config['encryption'];
        $mail->Port = $smtp_config['port'];
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom($smtp_config['username'], 'AceIT Website Form');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlTemplate;
        $mail->AltBody = 'Новая заявка с сайта AceIT. Для просмотра требуется HTML.';

        writeLog('INFO', "Attempting to send email to: $to");
        
        if (!$mail->send()) {
            throw new Exception("Mailer Error: " . $mail->ErrorInfo);
        }
        
        writeLog('SUCCESS', "Email sent successfully to: $to");
        return true;
        
    } catch (Exception $e) {
        writeLog('ERROR', "Failed to send email to $to: " . $e->getMessage());
        throw $e; // Пробрасываем ошибку дальше
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        writeLog('INFO', 'Starting email dispatch process');

        $subject = 'Новая заявка с сайта';
        $allSuccess = true;
        $errors = [];
        $sentTo = [];

        foreach ($recipients as $to) {
            try {
                sendEmail($to, $subject);
                $sentTo[] = $to;
            } catch (Exception $e) {
                $allSuccess = false;
                $errors[] = "Failed to send to $to: " . $e->getMessage();
            }
        }

        if ($allSuccess) {
            writeLog('SUCCESS', 'All emails sent successfully', ['recipients' => $sentTo]);
            echo json_encode([
                'success' => true,
                'message' => 'Все письма успешно отправлены'
            ]);
        } else {
            throw new Exception("Ошибки при отправке:\n" . implode("\n", $errors));
        }

    } catch (Exception $e) {
        writeLog('ERROR', 'Fatal error in main process', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка при отправке: ' . $e->getMessage(),
            'details' => $errors ?? []
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    writeLog('ERROR', 'Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false,
        'message' => 'Метод не разрешен'
    ], JSON_UNESCAPED_UNICODE);
}
?>
