<?php

$api_key = 'SUA_API_KEY_AQUI'; // Chave da API

// Função para criar uma cobrança PIX
function criarCobrancaPix($valor, $cpf, $nomeDevedor, $referencia, $solicitacaoPagador, $webhookUrl) {
    global $api_key;
    $url = "https://api.polopag.com.br/v1/cobpix"; // URL da API

    // Dados da cobrança
    $dados = [
        "valor" => $valor,
        "calendario" => [
            "expiracao" => 3600 // Expiração em segundos (1 hora)
        ],
        "isDeposit" => false, // Se for true, o valor fica como saldo
        "referencia" => $referencia, // Identificação interna do pedido
        "solicitacaoPagador" => $solicitacaoPagador,
        "devedor" => [
            "cpf" => $cpf,
            "nome" => $nomeDevedor
        ],
        "infoAdicionais" => [
            [
                "nome" => "Info adicional 1",
                "valor" => "Exemplo de valor adicional"
            ]
        ],
        "webhookUrl" => $webhookUrl // URL para receber notificações
    ];

    // Converte o array de dados em JSON
    $dadosJson = json_encode($dados);

    // Inicializa o cURL
    $ch = curl_init($url);

    // Configurações do cURL
    curl_setopt($ch, CURLOPT_POST, true); // Indica que é uma requisição POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dadosJson); // Passa os dados da cobrança
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retorna a resposta em vez de imprimir
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json', // Define o tipo de conteúdo
        'Content-Length: ' . strlen($dadosJson), // Comprimento dos dados enviados
        'Api-Key: ' . $api_key // Chave da API
    ]);

    // Executa a requisição e captura a resposta
    $resposta = curl_exec($ch);

    // Fecha a conexão cURL
    curl_close($ch);

    // Processa a resposta
    $respostaDecodificada = json_decode($resposta, true);
    if (isset($respostaDecodificada['qrcodeBase64'])) {
        $qrcodeBase64 = $respostaDecodificada['qrcodeBase64']; // QR Code em base64
        $pixCopiaECola = $respostaDecodificada['pixCopiaECola']; // Código "Pix Copia e Cola"
        $txid = $respostaDecodificada['txid']; // ID da transação

        echo "Cobrança criada com sucesso!\n";
        echo "QR Code: $qrcodeBase64\n";
        echo "Pix Copia e Cola: $pixCopiaECola\n";
        echo "TXID: $txid\n";

        return $txid;
    } else {
        echo "Erro ao criar cobrança PIX\n";
        return false;
    }
}

// Função para verificar o status do PIX
function verificarStatusPix($txid) {
    global $api_key;
    $url = "https://api.polopag.com.br/v1/check-pix/$txid"; // URL da API para verificar

    // Inicializa o cURL
    $ch = curl_init($url);

    // Configurações do cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retorna a resposta
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Api-Key: ' . $api_key // Chave da API
    ]);

    // Executa a requisição e captura a resposta
    $resposta = curl_exec($ch);

    // Fecha a conexão cURL
    curl_close($ch);

    // Processa a resposta
    $respostaDecodificada = json_decode($resposta, true);

    if (isset($respostaDecodificada['status'])) {
        if ($respostaDecodificada['status'] === 'ATIVA') {
            echo "A cobrança ainda não foi paga.\n";
        } else {
            echo "A cobrança foi paga! Valor: " . $respostaDecodificada['valor'] . "\n";
        }
    } else {
        echo "Erro ao verificar status do PIX\n";
    }
}

// Função para receber e processar o Webhook de pagamento
function receberWebhookPix() {
    // Receber o conteúdo do webhook (corpo da requisição)
    $input = file_get_contents('php://input');
    $webhookData = json_decode($input, true);

    // Log para verificar o que foi recebido
    file_put_contents('webhook_log.txt', print_r($webhookData, true), FILE_APPEND);

    // Verifica se o pagamento foi aprovado
    if (isset($webhookData['status']) && $webhookData['status'] === 'APROVADO') {
        $txid = $webhookData['txid'];
        $valor = $webhookData['valor'];
        $devedor = $webhookData['devedor']['nome'];

        // Atualize o pedido no sistema com o pagamento aprovado
        echo "Pagamento de R$ $valor do cliente $devedor para o TXID: $txid aprovado!\n";
    } else {
        echo "Pagamento não aprovado.\n";
    }
}

// Exemplo de uso:

// 1. Criar uma cobrança PIX
$txid = criarCobrancaPix("50.00", "68226428629", "João da Silva", "9989", "Minha Loja - Pedido 9989", "https://www.seusite.com.br/webhook");

// 2. Verificar o status do PIX (usando o TXID gerado anteriormente)
if ($txid) {
    verificarStatusPix($txid);
}

// 3. Receber o Webhook (essa função seria chamada automaticamente quando o webhook for enviado pela API)
receberWebhookPix();
