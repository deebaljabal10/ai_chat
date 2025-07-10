<?php
// ai_telegram_bot.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// إعدادات البوت
define('BOT_TOKEN', '8019685042:AAGTnejblo6pq7ER1HMErMRvPMHhfu5ahIQ');
define('OPENAI_API_KEY', 'sk-a8f29c1ad79f4fb1927860086cd90402');
define('TELEGRAM_API', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');
define('OPENAI_API', 'https://api.openai.com/v1/chat/completions');

// سجل الأخطاء
function logError($error) {
    file_put_contents('bot_errors.log', date('[Y-m-d H:i:s] ').$error.PHP_EOL, FILE_APPEND);
}

// إرسال طلبات HTTP
function sendRequest($url, $method = 'POST', $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = 'CURL Error: '.curl_error($ch);
        curl_close($ch);
        throw new Exception($error);
    }
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        throw new Exception("HTTP $httpCode: ".$response);
    }
    
    return json_decode($response, true);
}

// إرسال رسالة إلى المستخدم
function sendTelegramMessage($chatId, $text, $replyTo = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($replyTo) {
        $data['reply_to_message_id'] = $replyTo;
    }
    
    return sendRequest(TELEGRAM_API.'sendMessage', 'POST', $data);
}

// الحصول على رد من الذكاء الاصطناعي
function getAIResponse($message) {
    $headers = ['Authorization: Bearer '.OPENAI_API_KEY];
    
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful Arabic-speaking assistant.'],
            ['role' => 'user', 'content' => $message]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1000
    ];
    
    $response = sendRequest(OPENAI_API, 'POST', $data, $headers);
    
    return $response['choices'][0]['message']['content'] ?? 'عذرًا، لم أتمكن من معالجة طلبك.';
}

// معالجة الأوامر الرئيسية
function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $messageId = $message['message_id'];
    
    try {
        // إعلام المستخدم أن البوت يكتب
        sendRequest(TELEGRAM_API.'sendChatAction', 'POST', [
            'chat_id' => $chatId,
            'action' => 'typing'
        ]);
        
        switch ($text) {
            case '/start':
                $welcome = "مرحبًا بك في بوت الدردشة الذكية! 🤖\n\n";
                $welcome .= "يمكنك التحدث معي كما تتحدث مع صديق.\n";
                $welcome .= "جرب أن تسألني أي سؤال أو تطلب مني المساعدة في موضوع ما.\n\n";
                $welcome .= "الأوامر المتاحة:\n";
                $welcome .= "/start - بدء المحادثة\n";
                $welcome .= "/help - عرض المساعدة\n";
                $welcome .= "/about - معلومات عن البوت";
                sendTelegramMessage($chatId, $welcome);
                break;
                
            case '/help':
                sendTelegramMessage($chatId, "كيف يمكنني مساعدتك؟ فقط اكتب رسالتك وسأجيب عليك باستخدام الذكاء الاصطناعي المتقدم.");
                break;
                
            case '/about':
                $about = "✨ <b>AI Chat Bot</b> ✨\n\n";
                $about .= "الإصدار: 2.0\n";
                $about .= "التقنية: OpenAI GPT-3.5\n";
                $about .= "اللغة: العربية (يدعم لغات أخرى)";
                sendTelegramMessage($chatId, $about);
                break;
                
            default:
                if (!empty($text)) {
                    $response = getAIResponse($text);
                    sendTelegramMessage($chatId, $response, $messageId);
                }
        }
    } catch (Exception $e) {
        logError("Handle Message Error: ".$e->getMessage());
        sendTelegramMessage($chatId, "⚠ حدث خطأ أثناء معالجة طلبك. يرجى المحاولة لاحقًا.");
    }
}

// المعالجة الرئيسية
try {
    $update = json_decode(file_get_contents('php://input'), true);
    
    if (isset($update['message'])) {
        handleMessage($update['message']);
    }
    
    // للتحقق من أن البوت يعمل
    echo 'Bot is running!';
} catch (Exception $e) {
    logError("Main Error: ".$e->getMessage());
    http_response_code(500);
    echo 'An error occurred';
}