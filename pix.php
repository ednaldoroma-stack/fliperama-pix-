<?php
// Configurações Principais
define('TOKEN_MERCADOPAGO', 'APP_USR-4044152357629706-091923-3ac388bd0b6510270dbce77a88a2bf33-3699720123');
define('VALOR_CREDITO', 2.00); // Valor em reais da ficha (ex: 2.00 = R$ 2,00)
define('ARQUIVO_STATUS', 'status_pagamento.txt');

// Captura a ação que o ESP32 ou o cliente está pedindo
$acao = isset($_GET['acao']) ? $_GET['acao'] : '';

// 1. AÇÃO: ESP32 PEDE PARA GERAR UM NOVO PIX
if ($acao == 'gerar') {
    $url = "https://mercadopago.com";
    
    // Identificador único para a transação não repetir no banco
    $id_transacao = time() . rand(100, 999);
    
    // Dados obrigatórios que o Mercado Pago exige para criar um Pix
    $dados = [
        "transaction_amount" => VALOR_CREDITO,
        "description" => "Credito Fliperama Pix",
        "payment_method_id" => "pix",
        "payer" => [
            "email" => "fliperama@teste.com",
            "first_name" => "Jogador",
            "last_name" => "Fliper",
            "identification" => ["type" => "CPF", "number" => "00000000000"]
        ]
    ];

    $headers = [
        "Authorization: Bearer " . TOKEN_MERCADOPAGO,
        "Content-Type: application/json",
        "X-Idempotency-Key: " . $id_transacao
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    $resposta = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($resposta, true);

    if (isset($json['point_of_interaction']['transaction_data']['qr_code'])) {
        // Pega o código "Copia e Cola" e o ID do pagamento gerado pelo banco
        $qr_code_copia_cola = $json['point_of_interaction']['transaction_data']['qr_code'];
        $payment_id = $json['id'];

        // Reseta o arquivo de status para "pendente" salvando o ID do pagamento atual
        file_put_contents(ARQUIVO_STATUS, $payment_id . "|pendente");

        // Retorna o texto puro para o ESP32 ler facilmente
        echo "OK|" . $qr_code_copia_cola;
    } else {
        // Se der erro, mostra a resposta real do Mercado Pago na tela para sabermos o motivo
        echo "ERRO BANCO: " . $resposta;
    }
    exit;
}

// 2. AÇÃO: ESP32 VERIFICA SE O PIX FOI PAGO
if ($acao == 'checar') {
    if (!file_exists(ARQUIVO_STATUS)) {
        echo "0"; // Sem transações registradas
        exit;
    }

    $conteudo = file_get_contents(ARQUIVO_STATUS);
    list($payment_id, $status_atual) = explode("|", $conteudo);

    // Se no arquivo já consta como aprovado, avisa o ESP32 e limpa o arquivo
    if ($status_atual == 'aprovado') {
        echo "1"; // Libera o crédito!
        file_put_contents(ARQUIVO_STATUS, "0|limpo");
        exit;
    }

    // Caso contrário, vai até o Mercado Pago checar o status em tempo real
    $url = "https://mercadopago.com" . $payment_id;
    $headers = ["Authorization: Bearer " . TOKEN_MERCADOPAGO];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resposta = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($resposta, true);

    if (isset($json['status']) && $json['status'] == 'approved') {
        echo "1"; // Dinheiro caiu! Libera crédito.
        file_put_contents(ARQUIVO_STATUS, "0|limpo"); // Reseta para não dar créditos infinitos
    } else {
        echo "0"; // Ainda não pagou
    }
    exit;
}

echo "Acao invalida.";
?>
