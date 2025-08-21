<?php
// includes/ai.php

require __DIR__ . '/../vendor/autoload.php';

// Load .env từ gốc project
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/..");
$dotenv->load();

/**
 * Lấy OpenAI API Key từ .env
 */
function getOpenAIKey(): ?string
{
    return $_ENV['OPENAI_API_KEY'] ?? null;
}

/**
 * Gọi API OpenAI Chat Completions để tạo tóm tắt
 */
function createSummary(string $text): string
{
    $apiKey = getOpenAIKey();
    if (!$apiKey) return "⚠️ Missing API Key";

    $url = "https://api.openai.com/v1/chat/completions";

    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "Bạn là một trợ lý AI, hãy tóm tắt ngắn gọn nội dung văn bản."],
            ["role" => "user", "content" => $text]
        ],
        "max_tokens" => 150,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$result || $httpCode !== 200) return "";

    $response = json_decode($result, true);
    return $response['choices'][0]['message']['content'] ?? "";
}

/**
 * Gọi API OpenAI Embeddings
 */
function createEmbedding(string $text): array
{
    $apiKey = getOpenAIKey();
    if (!$apiKey) return [];

    $url = "https://api.openai.com/v1/embeddings";

    $data = [
        "model" => "text-embedding-3-small",
        "input" => $text,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$result || $httpCode !== 200) return [];

    $response = json_decode($result, true);
    return $response['data'][0]['embedding'] ?? [];
}
