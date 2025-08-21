<?php
// includes/ai.php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/..");
$dotenv->load();

function getOpenAIKey()
{
    return $_ENV["OPENAI_API_KEY"] ?? null;
}

/**
 * Tạo tóm tắt văn bản
 */
function createSummary($text)
{
    $apiKey = getOpenAIKey();
    if (!$apiKey) {
        return "⚠️ Missing API Key";
    }

    $url = "https://api.openai.com/v1/chat/completions";

    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "Bạn là một trợ lý AI, hãy tóm tắt ngắn gọn nội dung văn bản."],
            ["role" => "user", "content" => $text]
        ],
        "max_tokens" => 150,
    ];

    $options = [
        "http" => [
            "header" => "Content-Type: application/json\r\nAuthorization: Bearer $apiKey\r\n",
            "method" => "POST",
            "content" => json_encode($data),
        ],
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        return "";
    }

    $response = json_decode($result, true);
    return $response['choices'][0]['message']['content'] ?? "";
}

/**
 * Tạo embedding vector từ văn bản
 */
function createEmbedding($text)
{
    $apiKey = getOpenAIKey();
    if (!$apiKey) {
        return [];
    }

    $url = "https://api.openai.com/v1/embeddings";

    $data = [
        "model" => "text-embedding-3-small",
        "input" => $text,
    ];

    $options = [
        "http" => [
            "header" => "Content-Type: application/json\r\nAuthorization: Bearer $apiKey\r\n",
            "method" => "POST",
            "content" => json_encode($data),
        ],
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        return [];
    }

    $response = json_decode($result, true);
    return $response['data'][0]['embedding'] ?? [];
}
