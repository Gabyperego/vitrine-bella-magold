<?php
/**
 * Salvar Novo Ticket — dsoft Suporte
 */
require_once __DIR__ . '/../config/auth.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../lancamentos.php');
    exit;
}

$numeroTicket = trim($_POST['numero_ticket'] ?? '');
$analistaNome = trim($_POST['analista_nome'] ?? '');
$clienteEmpresa = trim($_POST['cliente_empresa'] ?? '');
$dataHora = trim($_POST['data_hora'] ?? date('Y-m-d\TH:i'));
$tipo = trim($_POST['tipo'] ?? 'Outro');
$status = trim($_POST['status'] ?? 'Em Aberto');
$prioridade = trim($_POST['prioridade'] ?? 'Média');
$descricao = trim($_POST['descricao'] ?? '');

if (empty($numeroTicket) || empty($descricao)) {
    setFlash('error', 'Número do ticket e descrição são obrigatórios.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../lancamentos.php'));
    exit;
}

$db = Database::getInstance();

$novoTicket = [
    'numero_ticket' => $numeroTicket,
    'analista_nome' => $analistaNome,
    'cliente_empresa' => $clienteEmpresa,
    'data_hora' => str_replace('T', ' ', $dataHora) . ':00',
    'tipo' => $tipo,
    'status' => $status,
    'prioridade' => $prioridade,
    'descricao' => $descricao,
    'concluido_em' => $status === 'Concluído' ? date('Y-m-d H:i:s') : null
];

$db->addTicket($novoTicket);

setFlash('success', "Ticket #{$numeroTicket} registrado com sucesso na agenda da equipe!");
header('Location: ../lancamentos.php');
exit;
