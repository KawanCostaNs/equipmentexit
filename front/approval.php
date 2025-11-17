<?php

include ('../../../inc/includes.php'); 
use GlpiPlugin\Equipmentexit\Request as PluginEquipmentexitRequest;

global $DB, $CFG_GLPI;
$plugin_name = 'equipmentexit';
$user_id = Session::getLoginUserID(); 

function checkUserRole($table_name, $user_id) {
    global $DB;
    $iterator = $DB->request([
        'FROM' => $table_name,
        'WHERE' => ['users_id' => $user_id]
    ]);
    return count($iterator) > 0;
}

$is_gerente    = checkUserRole('glpi_plugin_equipmentexit_gerentes', $user_id);
$is_governanca = checkUserRole('glpi_plugin_equipmentexit_governanca', $user_id);
$is_seguranca  = checkUserRole('glpi_plugin_equipmentexit_seguranca', $user_id);

$is_admin = Session::haveRight('config', UPDATE);
if (!$is_admin && !$is_gerente && !$is_governanca && !$is_seguranca) {
    Html::displayRightError();
}

// --- Processamento POST ---
$redirect_url = $CFG_GLPI['root_doc'] . "/plugins/$plugin_name/front/approval.php";
if (isset($_POST['action']) && isset($_POST['request_id']) && $_POST['request_id'] > 0) {
    
    $request_id = (int)$_POST['request_id'];
    $comment    = $_POST['comment'] ?? '';
    $action     = $_POST['action']; 

    $table = PluginEquipmentexitRequest::getTable();
    
    $iterator = $DB->request([
        'SELECT' => 'status',
        'FROM'   => $table,
        'WHERE'  => ['id' => $request_id]
    ]);
    
    if (count($iterator) == 0) {
        Session::addMessageAfterRedirect(__('Solicitação não encontrada.', $plugin_name), true, ERROR);
        Html::redirect($redirect_url);
    }
    $current_status_row = $iterator->current();
    $current_status = $current_status_row['status'];
    $update_data = [];
    $new_status = null;
    $message = "";

    if ($action == 'approve') {
        switch ($current_status) {
            case 1: 
                if ($is_gerente || $is_admin) { 
                     $new_status = 2; 
                    $update_data = ['status' => $new_status, 'users_id_gerente' => $user_id, 'date_gerente' => $_SESSION["glpi_currenttime"], 'comment_gerente'  => $comment];
                    $message = __('Aprovação do Gerente registrada. Aguardando Governança.', $plugin_name);
                }
                 break;
            case 2: 
                if ($is_governanca || $is_admin) { 
                     $new_status = 3; 
                    $update_data = ['status' => $new_status, 'users_id_governanca' => $user_id, 'date_governanca' => $_SESSION["glpi_currenttime"], 'comment_governanca'  => $comment];
                    $message = __('Aprovação da Governança registrada. Aguardando Segurança da Saída.', $plugin_name);
                }
                 break;
            case 3: 
                if ($is_seguranca || $is_admin) { 
                     $new_status = 4; 
                    $update_data = ['status' => $new_status, 'users_id_seg_saida' => $user_id, 'date_seg_saida' => $_SESSION["glpi_currenttime"], 'comment_seg_saida'  => $comment];
                    $message = __('Saída do equipamento registrada. Aguardando Segurança da Chegada.', $plugin_name);
                }
                break;
            case 4: 
                if ($is_seguranca || $is_admin) { 
                     $new_status = 5; 
                    $update_data = ['status' => $new_status, 'users_id_seg_chegada' => $user_id, 'date_seg_chegada' => $_SESSION["glpi_currenttime"], 'comment_seg_chegada'  => $comment];
                    $message = __('Chegada do equipamento registrada. Fluxo concluído!', $plugin_name);
                }
                break;
        }
    } else if ($action == 'reject') {
        if ($current_status == 1 && ($is_gerente || $is_admin)) {
             $new_status = 9; 
            $update_data = ['status' => $new_status, 'users_id_gerente' => $user_id, 'date_gerente' => $_SESSION["glpi_currenttime"], 'comment_gerente'  => $comment];
            $message = __('Solicitação REJEITADA pelo Gerente.', $plugin_name);
        } else if ($current_status == 2 && ($is_governanca || $is_admin)) {
            $new_status = 9; 
            $update_data = ['status' => $new_status, 'users_id_governanca' => $user_id, 'date_governanca' => $_SESSION["glpi_currenttime"], 'comment_governanca'  => $comment];
            $message = __('Solicitação REJEITADA pela Governança.', $plugin_name);
        }
    }

    if ($new_status !== null && !empty($update_data)) {
        $DB->update(PluginEquipmentexitRequest::getTable(), $update_data, ['id' => $request_id]);
        Session::addMessageAfterRedirect($message, true, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Ação não permitida ou esta solicitação não está mais na sua fila.', $plugin_name), true, WARNING);
    }
    Html::redirect($redirect_url);
}

// --- Busca de Dados ---
$user_lojas = [];
if ($is_seguranca) {
    $iterator = $DB->request([
        'SELECT' => 'loja',
        'FROM'   => 'glpi_plugin_equipmentexit_seguranca',
        'WHERE'  => ['users_id' => $user_id]
    ]);
    foreach ($iterator as $row) {
        $user_lojas[] = $row['loja'];
    }
}

$where = [];
$where_or = [];

if ($is_gerente) { 
    $where_or[] = ['req.status' => 1]; 
}
if ($is_governanca) { 
    $where_or[] = ['req.status' => 2]; 
}

$req_table = PluginEquipmentexitRequest::getTable();
$item_table = PluginEquipmentexitRequest::$item_table; 

if ($is_seguranca && !empty($user_lojas)) {
    $lojas_escaped = implode("','", array_map([$DB, 'escape'], $user_lojas));
    
    $where_or[] = new \QueryExpression("(
        (req.status = 3 OR req.status = 4) AND
        (
            req.local_origem IN ('$lojas_escaped')
            OR
            (SELECT items_check.loja_destino 
             FROM `$item_table` AS items_check
             WHERE items_check.plugin_equipmentexit_requests_id = req.id
             ORDER BY items_check.id ASC
             LIMIT 1) IN ('$lojas_escaped')
        )
    )");
}

if ($is_admin) {
    $where_or[] = ['req.status' => [1, 2, 3, 4]];
}

if (empty($where_or)) {
    $where = ['id' => -1];
} else {
    $where[] = ['OR' => $where_or];
    $where['req.is_deleted'] = 0;
}

$iterator = $DB->request([
    'SELECT' => [
        'req.id', 'req.reason', 'req.status', 'req.date_request', 'req.date_exit_planned', 'req.date_return_planned', 'req.users_id_requester', 'req.local_origem',
        'users.name AS requester_username', 'users.realname AS requester_realname', 'users.firstname AS requester_firstname',
        new \QueryExpression("(SELECT GROUP_CONCAT(
            CONCAT(items.equipamento_nome, ' (Qtd: ', items.quantidade, ', Tipo: ', items.equipamento_tipo, ', Destino: ', items.loja_destino, ')') SEPARATOR '\n'
            ) FROM `$item_table` AS items WHERE items.plugin_equipmentexit_requests_id = req.id
        ) AS item_details_list"),
        new \QueryExpression("(SELECT items_first.loja_destino FROM `$item_table` AS items_first
            WHERE items_first.plugin_equipmentexit_requests_id = req.id ORDER BY items_first.id ASC LIMIT 1
        ) AS first_item_destino")
    ],
    'FROM' => "$req_table AS req",
    'INNER JOIN' => [
        'glpi_users AS users' => [
            'ON' => ['req' => 'users_id_requester', 'users' => 'id']
        ]
    ],
    'WHERE' => $where,
    'ORDER' => 'req.date_request ASC'
]);

$pending_requests = iterator_to_array($iterator);

function getStatusNameForQueue($status) {
    global $plugin_name;
    switch ($status) {
        case 1: return __('Pendente Gerente', $plugin_name);
        case 2: return __('Pendente Governança', $plugin_name);
        case 3: return __('Pendente Segurança Saída', $plugin_name);
        case 4: return __('Pendente Segurança Chegada', $plugin_name);
        case 5: return __('Concluído', $plugin_name);
        case 9: return __('Rejeitado', $plugin_name);
        default: return __('Desconhecido', $plugin_name);
    }
}

// --- Exibição ---
Html::header(
    __('Fila de Ações Pendentes', $plugin_name), 
    $_SERVER['PHP_SELF'],                   
    'plugins',                              
    'PluginEquipmentexitRequest',
    $plugin_name
);

echo '<link rel="stylesheet" type="text/css" href="'.$CFG_GLPI["root_doc"].'/plugins/equipmentexit/css/equipmentexit.css">';

echo "<div class='spaced center-h'>"; 
echo "<h2><i class='fas fa-tasks'></i> " . __('Solicitações Aguardando sua Ação', $plugin_name) . "</h2>";
echo "<div class='approval-card-container'>";

if (count($pending_requests) > 0) {
    foreach ($pending_requests as $request) {
        // *** CORREÇÃO: Html::clean -> htmlspecialchars ***
        $requester_display_name = htmlspecialchars($request['requester_firstname'] . " " . $request['requester_realname'], ENT_QUOTES);
        $status_display = getStatusNameForQueue($request['status']);
        $csrf_token = Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        
        $can_act = false;
        $comment_placeholder = __('Comentário (opcional)', $plugin_name);
        $approve_button_text = "";
        $show_reject_button = false;

        switch ($request['status']) {
            case 1: 
                $approve_button_text = __("Aprovar Etapa", $plugin_name);
                $comment_placeholder = __("Comentário de Aprovação (opcional)", $plugin_name);
                $show_reject_button = true;
                if ($is_gerente || $is_admin) $can_act = true;
                break;
            case 2: 
                $approve_button_text = __("Aprovar Etapa", $plugin_name);
                $comment_placeholder = __("Comentário de Aprovação (opcional)", $plugin_name);
                $show_reject_button = true;
                if ($is_governanca || $is_admin) $can_act = true;
                break;
            case 3: 
                $approve_button_text = __("Confirmar Saída", $plugin_name);
                $comment_placeholder = __("Obs. da Saída (opcional)", $plugin_name);
                $show_reject_button = false;
                if ($is_admin || ($is_seguranca && in_array($request['local_origem'], $user_lojas))) {
                     $can_act = true;
                }
                break;
            case 4: 
                $approve_button_text = __("Confirmar Chegada", $plugin_name);
                $comment_placeholder = __("Obs. da Chegada (opcional)", $plugin_name);
                $show_reject_button = false;
                 if ($is_admin || ($is_seguranca && in_array($request['first_item_destino'], $user_lojas))) {
                    $can_act = true;
                }
                break;
        }

        echo "<div class='approval-card status-" . $request['status'] . "'>";
        echo "<div class='card-header'>";
        echo "<span class='card-id'>" . sprintf(__('Solicitação #%d'), $request['id']) . "</span>";
        echo "<span class='card-status'>" . $status_display . "</span>";
        echo "</div>"; 
        echo "<div class='card-body'>";
        echo "<div class='card-section'>";
        echo "<div><strong class='card-label'>" . __('Solicitante:') . "</strong> " . $requester_display_name . "</div>";
        echo "<div><strong class='card-label'>" . __('Saída Prevista:') . "</strong> " . Html::convDateTime($request['date_exit_planned']) . "</div>";
        if (!empty($request['date_return_planned'])) {
             echo "<div><strong class='card-label'>" . __('Retorno Previsto:') . "</strong> " . Html::convDateTime($request['date_return_planned']) . "</div>";
        }
        echo "</div>";
        echo "<div class='card-section'>";
        echo "<strong class='card-label'>" . __('Justificativa:') . "</strong>";
        // *** CORREÇÃO: Html::clean -> htmlspecialchars ***
        echo "<div class='card-justificativa'>" . nl2br(htmlspecialchars($request['reason'], ENT_QUOTES)) . "</div>";
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
        echo "</div>"; 

        echo "<div class='card-footer approval-actions'>";
        if (!$can_act) {
            echo "<div class='card-awaiting-action'>";
            if ($is_seguranca && ($request['status'] == 3 || $request['status'] == 4)) {
                 echo "<i>" . __('Aguardando ação (outra loja)', $plugin_name) . "</i>";
            } else {
                echo "<i>" . __('Aguardando outra equipe', $plugin_name) . "</i>";
            }
            echo "</div>";
        } else {
            echo "<form method='post' action='$redirect_url' class='form-approve'>";
            echo $csrf_token;
            echo Html::hidden('request_id', ['value' => $request['id']]);
            echo Html::hidden('action', ['value' => 'approve']);
            echo "<input type='text' name='comment' placeholder='" . $comment_placeholder . "'>";
            echo "<button type='submit' name='approve_submit' class='btn btn-success'><i class='fas fa-check'></i> " . $approve_button_text . "</button>";
            echo "</form>";
            if ($show_reject_button) {
                echo "<form method='post' action='$redirect_url' class='form-reject'>";
                echo $csrf_token;
                echo Html::hidden('request_id', ['value' => $request['id']]);
                echo Html::hidden('action', ['value' => 'reject']);
                echo "<input type='text' name='comment' placeholder='" . __('Motivo da Rejeição (obrigatório se rejeitar)', $plugin_name) . "'>";
                echo "<button type='submit' name='reject_submit' class='btn btn-danger'><i class='fas fa-times'></i> " . __('Rejeitar', $plugin_name) . "</button>";
                echo "</form>";
            }
        }
        echo "</div>"; 
        echo "</div>"; 
    }
} else {
    echo "<div class='card-empty-message'>";
    echo "<i class='fas fa-check-circle' style='font-size: 3em; color: #5cb85c;'></i>";
    echo "<p style='font-size: 1.2em; margin-top: 10px;'>" . __('Nenhuma solicitação aguardando sua ação no momento.', $plugin_name) . "</p>";
    echo "</div>";
}
echo "</div>"; 
echo "</div>"; 
Html::footer();
?>