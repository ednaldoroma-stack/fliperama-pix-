<?php
// Configurações Principais
define('TOKEN_MERCADOPAGO', 'APP_USR-4044152357629706-091923-3ac388bd0b6510270dbce77a88a2bf33-3699720123'); // Substitua pela sua chave real
define('VALOR_CREDITO', 2.00); // Valor em reais da ficha
define('ARQUIVO_STATUS', 'status_pagamento.txt');

$acao = isset($_GET['acao']) ? $_GET['acao'] : '';

// 1. AÇÃO: GERAR UM NOVO PIX FORÇANDO BRASIL
if ($acao == 'gerar') {
    $url = "https://mercadopago.com";
    $id_transacao = time() . rand(100, 999);
    
    // Forçando a moeda local e dados estruturados no formato brasileiro
    $dados = [
        "transaction_amount" => VALOR_CREDITO,
        "description" => "Credito Fliperama Pix",
        "payment_method_id" => "pix",
        "currency_id" => "BRL", // FORÇA A MOEDA EM REAL BRASILEIRO
        "payer" => [
            "email" => "test_user_fliperama@testuser.com", // Padrão aceito pela API
            "first_name" => "Jogador",
            "last_name" => "Fliper",
            "identification" => [
                "type" => "CPF", 
                "number" => "19100000000" // CPF fictício válido para testes da API brasileira
            ]
        ]
    ];

    $headers = [
        "Authorization: Bearer " . TOKEN_MERCADOPAGO,
        "Content-Type: application/json",
        "X-Idempotency-Key: " . $id_transacao,
        "X-Melicountry: MLA" // Adiciona o cabeçalho explícito para forçar roteamento correto
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    $resposta = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($resposta, true);

    if (isset($json['point_of_interaction']['transaction_data']['qr_code'])) {
        $qr_code_copia_cola = $json['point_of_interaction']['transaction_data']['qr_code'];
        $payment_id = $json['id'];
        file_put_contents(ARQUIVO_STATUS, $payment_id . "|pendente");
        echo "OK|" . $qr_code_copia_cola;
    } else {
        echo "ERRO BANCO: " . $resposta;
    }
    exit;
}

// 2. AÇÃO: CHECKAR STATUS DO PAGAMENTO
if ($acao == 'checar') {
    if (!file_exists(ARQUIVO_STATUS)) {
        echo "0";
        exit;
    }

    $conteudo = file_get_contents(ARQUIVO_STATUS);
    list($payment_id, $status_atual) = explode("|", $conteudo);

    if ($status_atual == 'aprovado') {
        echo "1";
        file_put_contents(ARQUIVO_STATUS, "0|limpo");
        exit;
    }

    $url = "https://mercadopago.com/" . $payment_id;
    $headers = ["Authorization: Bearer " . TOKEN_MERCADOPAGO];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $resposta = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($resposta, true);

    if (isset($json['status']) && $json['status'] == 'approved') {
        echo "1";
        file_put_contents(ARQUIVO_STATUS, "0|limpo");
    } else {
        echo "0";
    }
    exit;
}

echo "Acao invalida.";
?>
