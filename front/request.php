<?php


include ('../../../inc/includes.php');
require_once(__DIR__ . '/../inc/Request.php');
use GlpiPlugin\Equipmentexit\Request as PluginEquipmentexitRequest;

global $DB;
$plugin_name = 'equipmentexit';
$user_id = Session::getLoginUserID();
if (empty($user_id)) {
    Html::displayRightError();
}

// --- Lógica de Permissão (v3.1.2 - CORRIGIDA) ---
$can_access = false; 
$can_create = false; 
if (Session::haveRight('config', UPDATE)) {
    $can_access = true;
    $can_create = true;
}

/**
 * (v3.1.2) CORRIGIDO: Trocado $DB->request por $DB->query
 */
function checkRoleAccess($table_name, $user_id) {
    global $DB;
    // Usa SQL bruto (raw SQL) que sabemos que funciona
    $sql_check = "SELECT COUNT(*) as cpt FROM `$table_name` WHERE `users_id` = " . (int)$user_id;
    $check_result = $DB->query($sql_check);
    
    if ($check_result && ($count_row = $DB->fetchAssoc($check_result)) && $count_row['cpt'] > 0) {
        return true;
    }
    return false;
}

// O usuário pode acessar esta página se for Admin OU estiver em QUALQUER uma das 4 listas
if (!$can_access) {
    if (checkRoleAccess('glpi_plugin_equipmentexit_requesters', $user_id)) {
        $can_access = true;
        $can_create = true; // Solicitantes podem criar
    }
}
if (!$can_access) {
    if (checkRoleAccess('glpi_plugin_equipmentexit_gerentes', $user_id)) { $can_access = true; }
}
if (!$can_access) {
    if (checkRoleAccess('glpi_plugin_equipmentexit_governanca', $user_id)) { $can_access = true; }
}
if (!$can_access) {
    if (checkRoleAccess('glpi_plugin_equipmentexit_seguranca', $user_id)) { $can_access = true; }
}

// Se o $rightname = 'self-service' falhou E ele não está em nenhuma lista, bloqueia.
if (!$can_access) {
    // NOTA: A permissão 'self-service' do inc/Request.php já deve bloquear
    // usuários que não têm acesso ao helpdesk. Esta é uma segunda camada.
    Html::displayRightError();
}
// --- FIM DA PERMISSÃO ---


// --- Busca de Dados (v3.1 - Correto) ---
$req_table = PluginEquipmentexitRequest::getTable();
$item_table = PluginEquipmentexitRequest::$item_table;

$sql_my_requests = "SELECT
                        req.id,
                        req.status,
                        req.date_request,
                        req.local_origem,
                        req.tipo_movimentacao,
                        
                        (SELECT GROUP_CONCAT(
                            CONCAT(
                                items.equipamento_nome, 
                                ' (Qtd: ', items.quantidade, 
                                ', Destino: ', items.loja_destino, ')'
                            ) SEPARATOR '\n'
                        ) 
                        FROM `$item_table` AS items 
                        WHERE items.plugin_equipmentexit_requests_id = req.id
                        ) AS item_details_list
                        
                    FROM
                        `$req_table` AS req
                    WHERE
                        req.users_id_requester = $user_id
                        AND req.is_deleted = 0
                    ORDER BY
                        req.date_request DESC";

$requests_query = $DB->query($sql_my_requests);
if (!$requests_query) {
    Html::header(__('Minhas Solicitações', $plugin_name), $_SERVER['PHP_SELF'], 'plugins', $plugin_name);
    Html::displayErrorAndDie("Erro ao buscar solicitações: " . $DB->error());
}
$my_requests = iterator_to_array($requests_query);


// --- Exibição da Página (v3.1 - Card Layout) ---
Html::header(
    __('Minhas Solicitações de Saída', $plugin_name),
    $_SERVER['PHP_SELF'],
    'knowbase', 
    'PluginEquipmentexitRequest',
    $plugin_name
);
echo "<div class='spaced center-h'>";

if ($can_create) { 
    echo "<div class='center spaced'>";
    echo "<a href='request.form.php' class='vsubmit'>" . __('Nova Solicitação', $plugin_name) . "</a>";
    echo "</div><br>";
}

echo "<div class='request-card-container'>"; 

if (count($my_requests) > 0) {
    foreach ($my_requests as $request) {
        
        $status_name = PluginEquipmentexitRequest::getStatusName($request['status']); 
        $tipo_mov = ucfirst($request['tipo_movimentacao'] ?? __('N/D'));
        $edit_link = "request.form.php?id=" . $request['id'];
        $print_link = "print.php?id=" . $request['id'];

        echo "<div class='request-card status-" . $request['status'] . "'>";

        // Cabeçalho do Card
        echo "<div class='card-header'>"; 
        echo "<span class='card-id'>" . sprintf(__('Solicitação #%d'), $request['id']) . "</span>";
        echo "<span class='card-status'>" . $status_name . "</span>";
        echo "</div>"; 

        // Corpo do Card
        echo "<div class='card-body'>"; 
        
        // Detalhes
        echo "<div class='card-section'>";
        echo "<div><strong class='card-label'>" . __('Data:') . "</strong> " . Html::convDateTime($request['date_request']) . "</div>";
        echo "<div><strong class='card-label'>" . __('Origem:') . "</strong> " . Html::clean($request['local_origem']) . "</div>";
        echo "<div><strong class='card-label'>" . __('Movimentação:') . "</strong> " . Html::clean($tipo_mov) . "</div>";
        echo "</div>";

        // Itens
        echo "<div class='card-section'>";
        echo "<strong class='card-label'>" . __('Itens da Solicitação:') . "</strong>";
        
        if (!empty($request['item_details_list'])) {
            $items = explode("\n", $request['item_details_list']);
            echo "<ul class='card-item-list'>"; 
            foreach ($items as $item) {
                echo "<li><i class='fas fa-caret-right'></i> " . Html::clean($item) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<div style='padding-left: 10px;'><i>" . __('Nenhum item detalhado.') . "</i></div>";
        }
        echo "</div>";
        
        echo "</div>"; // Fim card-body

        // Rodapé (Ações)
        echo "<div class='card-footer request-actions'>"; 
        
        if ($request['status'] == 1) {
            echo "<a href='$edit_link' class='btn btn-primary'><i class='fas fa-pencil-alt'></i> " . __('Editar') . "</a>";
        } else {
             echo "<a href='$edit_link' class='btn btn-secondary'><i class='far fa-eye'></i> " . __('Visualizar') . "</a>";
        }

        if ($request['status'] >= 3 && $request['status'] != 9) {
             echo "<a href='$print_link' class='btn btn-secondary' target='_blank'><i class='fas fa-print'></i> " . __('Imprimir') . "</a>";
        }
        
        echo "</div>"; // Fim card-footer
        echo "</div>"; // --- Fim do Card ---
    }
} else {
    // Mensagem de Nenhuma solicitação
    echo "<div class='card-empty-message'>"; 
    echo "<i class='fas fa-folder-open' style='font-size: 3em; color: #777;'></i>";
    echo "<p style='font-size: 1.2em; margin-top: 10px;'>" . __('Nenhuma solicitação encontrada.', $plugin_name) . "</p>";
    echo "</div>";
}

echo "</div>"; // Fim request-card-container
echo "</div>"; // Fim spaced center-h
Html::footer();
?>