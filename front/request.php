<?php

include ('../../../inc/includes.php');
use GlpiPlugin\Equipmentexit\Request as PluginEquipmentexitRequest;

global $DB, $CFG_GLPI;
$plugin_name = 'equipmentexit';
$user_id = Session::getLoginUserID();

if (empty($user_id)) {
    Html::displayRightError();
}

$can_access = false; 
$can_create = false; 
if (Session::haveRight('config', UPDATE)) {
    $can_access = true;
    $can_create = true;
}

function checkRoleAccess($table_name, $user_id) {
    global $DB;
    $iterator = $DB->request([
        'FROM' => $table_name,
        'WHERE' => ['users_id' => $user_id]
    ]);
    return count($iterator) > 0;
}

if (!$can_access) {
    if (checkRoleAccess('glpi_plugin_equipmentexit_requesters', $user_id)) {
        $can_access = true;
        $can_create = true;
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

if (!$can_access) {
    Html::displayRightError();
}

$req_table = PluginEquipmentexitRequest::getTable();
$item_table = PluginEquipmentexitRequest::$item_table;

$iterator = $DB->request([
    'SELECT' => [
        'req.id', 'req.status', 'req.date_request', 'req.local_origem', 'req.tipo_movimentacao',
        new \QueryExpression("(SELECT GROUP_CONCAT(
            CONCAT(items.equipamento_nome, ' (Qtd: ', items.quantidade, ', Destino: ', items.loja_destino, ')') SEPARATOR '\n'
            ) FROM `$item_table` AS items WHERE items.plugin_equipmentexit_requests_id = req.id
        ) AS item_details_list")
    ],
    'FROM' => "$req_table AS req",
    'WHERE' => [
        'req.users_id_requester' => $user_id,
        'req.is_deleted' => 0
    ],
    'ORDER' => 'req.date_request DESC'
]);

$my_requests = iterator_to_array($iterator);

Html::header(
    __('Minhas Solicitações de Saída', $plugin_name),
    $_SERVER['PHP_SELF'],
    'knowbase', 
    'PluginEquipmentexitRequest',
    $plugin_name
);

echo '<link rel="stylesheet" type="text/css" href="'.$CFG_GLPI["root_doc"].'/plugins/equipmentexit/css/equipmentexit.css">';

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
        echo "<div class='card-header'>"; 
        echo "<span class='card-id'>" . sprintf(__('Solicitação #%d'), $request['id']) . "</span>";
        echo "<span class='card-status'>" . $status_name . "</span>";
        echo "</div>"; 
        echo "<div class='card-body'>"; 
        echo "<div class='card-section'>";
        echo "<div><strong class='card-label'>" . __('Data:') . "</strong> " . Html::convDateTime($request['date_request']) . "</div>";
        // *** CORREÇÃO: Html::clean -> htmlspecialchars ***
        echo "<div><strong class='card-label'>" . __('Origem:') . "</strong> " . htmlspecialchars($request['local_origem'], ENT_QUOTES) . "</div>";
        echo "<div><strong class='card-label'>" . __('Movimentação:') . "</strong> " . htmlspecialchars($tipo_mov, ENT_QUOTES) . "</div>";
        echo "</div>";
        echo "<div class='card-section'>";
        echo "<strong class='card-label'>" . __('Itens da Solicitação:') . "</strong>";
        if (!empty($request['item_details_list'])) {
            $items = explode("\n", $request['item_details_list']);
            echo "<ul class='card-item-list'>"; 
            foreach ($items as $item) {
                // *** CORREÇÃO: Html::clean -> htmlspecialchars ***
                echo "<li><i class='fas fa-caret-right'></i> " . htmlspecialchars($item, ENT_QUOTES) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<div style='padding-left: 10px;'><i>" . __('Nenhum item detalhado.') . "</i></div>";
        }
        echo "</div>";
        echo "</div>"; // Fim card-body
        echo "<div class='card-footer request-actions'>"; 
        if ($request['status'] == 1) {
            echo "<a href='$edit_link' class='btn btn-primary'><i class='fas fa-pencil-alt'></i> " . __('Editar') . "</a>";
        } else {
             echo "<a href='$edit_link' class='btn btn-secondary'><i class='far fa-eye'></i> " . __('Visualizar') . "</a>";
        }
        if ($request['status'] >= 3 && $request['status'] != 9) {
             echo "<a href='$print_link' class='btn btn-secondary' target='_blank'><i class='fas fa-print'></i> " . __('Imprimir') . "</a>";
        }
        echo "</div>"; 
        echo "</div>"; 
    }
} else {
    echo "<div class='card-empty-message'>"; 
    echo "<i class='fas fa-folder-open' style='font-size: 3em; color: #777;'></i>";
    echo "<p style='font-size: 1.2em; margin-top: 10px;'>" . __('Nenhuma solicitação encontrada.', $plugin_name) . "</p>";
    echo "</div>";
}

echo "</div>"; 
echo "</div>"; 
Html::footer();
?>