<?php

include ('../../../inc/includes.php');
use GlpiPlugin\Equipmentexit\Request as PluginEquipmentexitRequest;
use User;

if (!Session::haveRight('config', UPDATE)) {
    Html::displayRightError();
}

global $DB, $CFG_GLPI; 
$plugin_name = 'equipmentexit';
$base_url = $CFG_GLPI['root_doc'] . "/plugins/$plugin_name/front/config.php";

// *** Upload de Logo ***
if (isset($_POST["upload_logo"])) {
    $target_dir = __DIR__ . '/../images/';
    if (!is_dir($target_dir)) { @mkdir($target_dir, 0777, true); }
    $target_file = $target_dir . 'custom_logo.png'; 
    $uploadOk = 1;
    
    if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] == 0) {
        $imageFileType = strtolower(pathinfo($_FILES['logo_upload']['name'], PATHINFO_EXTENSION));
        $check = @getimagesize($_FILES['logo_upload']['tmp_name']);
        if($check === false) {
            Session::addMessageAfterRedirect(__('Arquivo não é uma imagem válida.', $plugin_name), true, ERROR);
            $uploadOk = 0;
        }
        if($imageFileType != "png") {
            Session::addMessageAfterRedirect(__('Desculpe, apenas arquivos PNG são permitidos.', $plugin_name), true, ERROR);
            $uploadOk = 0;
        }
        if ($uploadOk != 0) {
            if (move_uploaded_file($_FILES['logo_upload']['tmp_name'], $target_file)) {
                @chmod($target_file, 0644);
                Session::addMessageAfterRedirect(__('O novo logo foi salvo com sucesso.', $plugin_name), true, INFO);
            } else {
                $error = error_get_last();
                Session::addMessageAfterRedirect(__('Erro ao mover arquivo. Verifique permissões.', $plugin_name), true, ERROR);
            }
        }
    } else {
        Session::addMessageAfterRedirect(__('Nenhum arquivo enviado ou erro no upload.', $plugin_name), true, WARNING);
    }
    Html::redirect($base_url);
}

function addUserToRole($table_name, $post_user_id, $role_name, $loja = null) {
    global $DB, $CFG_GLPI, $plugin_name;
    $local_redirect_url = $CFG_GLPI['root_doc'] . "/plugins/equipmentexit/front/config.php";

    $user_id_to_add = (int)$_POST[$post_user_id];
    if ($user_id_to_add <= 0) { Html::redirect($local_redirect_url); return; }
    
    $insert_data = ['users_id' => $user_id_to_add];
    $role_name_display = $role_name;
    $criteria = ['users_id' => $user_id_to_add];

    if ($loja !== null) {
        if (empty($loja)) {
            Session::addMessageAfterRedirect(__('Você deve selecionar uma loja.', $plugin_name), true, WARNING);
            Html::redirect($local_redirect_url);
            return;
        }
        $insert_data['loja'] = $loja;
        $criteria['loja'] = $loja;
        $role_name_display = "$role_name (Loja: $loja)";
    }

    $iterator = $DB->request(['FROM' => $table_name, 'WHERE' => $criteria]);
    
    if (count($iterator) == 0) {
        $DB->insert($table_name, $insert_data); 
        Session::addMessageAfterRedirect(sprintf(__('%s adicionado com sucesso!', $plugin_name), $role_name_display), true, INFO);
    } else {
        Session::addMessageAfterRedirect(sprintf(__('Este usuário já está na lista.', $plugin_name)), true, WARNING);
    }
    Html::redirect($local_redirect_url);
}

function removeUserFromRole($table_name, $get_id, $role_name) {
    global $DB, $CFG_GLPI, $plugin_name;
    $local_redirect_url = $CFG_GLPI['root_doc'] . "/plugins/equipmentexit/front/config.php";

    if (isset($_GET[$get_id]) && $_GET[$get_id] > 0) {
         $DB->delete($table_name, ['id' => (int)$_GET[$get_id]]);
         Session::addMessageAfterRedirect(sprintf(__('%s removido com sucesso!', $plugin_name), $role_name), true, INFO);
         Html::redirect($local_redirect_url);
    }
}

if (isset($_POST['add_gerente'])) { addUserToRole('glpi_plugin_equipmentexit_gerentes', 'users_id_gerente', 'Gerente'); }
removeUserFromRole('glpi_plugin_equipmentexit_gerentes', 'delete_gerente_id', 'Gerente');

if (isset($_POST['add_governanca'])) { addUserToRole('glpi_plugin_equipmentexit_governanca', 'users_id_governanca', 'Membro da Governança'); }
removeUserFromRole('glpi_plugin_equipmentexit_governanca', 'delete_governanca_id', 'Membro da Governança');

if (isset($_POST['add_seguranca'])) { 
    $loja_selecionada = $_POST['loja_seguranca'] ?? null;
    addUserToRole('glpi_plugin_equipmentexit_seguranca', 'users_id_seguranca', 'Membro da Segurança', $loja_selecionada); 
}
removeUserFromRole('glpi_plugin_equipmentexit_seguranca', 'delete_seguranca_id', 'Membro da Segurança');

if (isset($_POST['add_requester'])) { addUserToRole('glpi_plugin_equipmentexit_requesters', 'users_id_requester', 'Solicitante'); }
removeUserFromRole('glpi_plugin_equipmentexit_requesters', 'delete_requester_id', 'Solicitante');

function getUsersInRole($table_name) {
    global $DB;
    $columns = ['role_table.id AS row_id', 'users.id AS users_id', 'users.name AS username', 'users.realname', 'users.firstname'];
    $order_by = ['users.name ASC'];

    if ($table_name == 'glpi_plugin_equipmentexit_seguranca') {
        $columns[] = 'role_table.loja';
        $order_by[] = 'role_table.loja ASC';
    }

    $iterator = $DB->request([
        'SELECT' => $columns,
        'FROM'   => "$table_name AS role_table",
        'INNER JOIN' => ['glpi_users AS users' => ['ON' => ['role_table' => 'users_id', 'users' => 'id']]],
        'ORDER' => $order_by
    ]);
    return iterator_to_array($iterator);
}

$current_gerentes = getUsersInRole('glpi_plugin_equipmentexit_gerentes');
$current_governanca = getUsersInRole('glpi_plugin_equipmentexit_governanca');
$current_seguranca = getUsersInRole('glpi_plugin_equipmentexit_seguranca');
$current_requesters = getUsersInRole('glpi_plugin_equipmentexit_requesters');

Html::header(__('Configuração', $plugin_name), $_SERVER['PHP_SELF'], 'setup', $plugin_name);

// *** CARREGAMENTO CSS CORRIGIDO ***
$plugin_web_url = Plugin::getWebDir('equipmentexit'); 
echo "<link rel='stylesheet' type='text/css' href='{$plugin_web_url}/css/equipmentexit.css?v=" . time() . "'>";

echo "<div class='center-layout'><div class='glpi_form spaced'>";
$csrf_token = Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

// Card Logo
echo "<div class='role-management-card'>"; 
echo "<div class='role-card-header'><h2><i class='fas fa-image'></i> " . __('Logo do PDF', $plugin_name) . "</h2><p>" . __('Upload de logo PNG para o PDF.', $plugin_name) . "</p></div>"; 
echo "<div class='role-card-footer'>"; 
echo "<form method='post' action='$base_url' enctype='multipart/form-data' class='add-user-form'>$csrf_token";
echo "<div class='form-input' style='flex-grow: 3;'><strong>" . __('Selecionar logo (.PNG)', $plugin_name) . "</strong><br><input type='file' name='logo_upload' accept='image/png' class='glpi_input' style='width: 100%; padding: 5px;'></div>";
echo "<div class='form-action'><input type='submit' name='upload_logo' value='" . __('Enviar Logo', $plugin_name) . "' class='submit'></div>";
Html::closeForm();
echo "</div></div>"; 

// Função Render
function displayRoleSection($title, $description, $current_users_list, $delete_param_name, $form_user_name, $form_submit_name, $form_name = '') {
    global $plugin_name, $csrf_token, $CFG_GLPI;
    $local_redirect_url = $CFG_GLPI['root_doc'] . "/plugins/equipmentexit/front/config.php";
    
    echo "<div class='role-management-card'>"; 
    echo "<div class='role-card-header'><h2>" . $title . "</h2><p>" . $description . "</p></div>"; 
    echo "<div class='role-card-body'><ul class='role-user-list'>";
    
    if (count($current_users_list) > 0) {
        foreach ($current_users_list as $user) {
            $delete_link = $local_redirect_url . "?" . $delete_param_name . "=" . $user['row_id'];
            $user_display_name = htmlspecialchars($user['firstname'] . " " . $user['realname'], ENT_QUOTES);
            $loja_display = (isset($user['loja']) && !empty($user['loja'])) ? " <strong style='color: #555;'>(" . htmlspecialchars($user['loja'], ENT_QUOTES) . ")</strong>" : '';

            echo "<li class='role-user-item'><div class='user-info'><span class='user-name'>$user_display_name $loja_display</span> <span class='user-login'>(" . $user['username'] . ")</span></div>";
            echo "<div class='user-actions'><a href='$delete_link' class='btn btn-danger-outline' onclick=\"return confirm('" . addslashes(__('Tem certeza?', $plugin_name)) . "');\"><i class='fas fa-trash-alt'></i> " . __('Remover') . "</a></div></li>";
        }
    } else {
        echo "<li class='role-user-item-empty'>" . __('Nenhum usuário configurado.', $plugin_name) . "</li>";
    }
    echo "</ul></div>"; 

    echo "<div class='role-card-footer'><form method='post' action='$local_redirect_url' class='add-user-form'>$csrf_token";
    echo "<div class='form-input'><strong>" . __('Adicionar Usuário', $plugin_name) . "</strong><br>";
    User::dropdown(['name' => $form_user_name, 'value' => 0, 'right' => 'all', 'width' => '100%']);
    echo "</div>";
    
    if ($form_name == 'seguranca') {
        echo "<div class='form-input'><strong>" . __('Loja', 'equipmentexit') . "</strong><br>";
        $stores = PluginEquipmentexitRequest::getStoreList();
        echo "<select name='loja_seguranca' class='glpi_input' style='width: 100%;'><option value=''>" . __('-- Selecione --') . "</option>";
        foreach ($stores as $store) {
            $safe_store = htmlspecialchars($store, ENT_QUOTES);
            echo "<option value=\"$safe_store\">$safe_store</option>";
        }
        echo "</select></div>";
    }

    echo "<div class='form-action'><input type='submit' name='$form_submit_name' value='" . __('Adicionar', $plugin_name) . "' class='submit'></div>";
    Html::closeForm();
    echo "</div></div>"; 
}

displayRoleSection("<i class='fas fa-user-tie'></i> " . __('Gerentes', $plugin_name), __('Usuários que podem aprovar a Etapa 1.', $plugin_name), $current_gerentes, 'delete_gerente_id', 'users_id_gerente', 'add_gerente');
displayRoleSection("<i class='fas fa-user-shield'></i> " . __('Governança', $plugin_name), __('Usuários que podem aprovar a Etapa 2.', $plugin_name), $current_governanca, 'delete_governanca_id', 'users_id_governanca', 'add_governanca');
displayRoleSection("<i class='fas fa-hard-hat'></i> " . __('Segurança Patrimonial', $plugin_name), __('Usuários que validam Saída/Chegada.', $plugin_name), $current_seguranca, 'delete_seguranca_id', 'users_id_seguranca', 'add_seguranca', 'seguranca');
displayRoleSection("<i class='fas fa-user-check'></i> " . __('Solicitantes Autorizados', $plugin_name), __('Usuários que podem criar solicitações.', $plugin_name), $current_requesters, 'delete_requester_id', 'users_id_requester', 'add_requester');

echo "</div></div>";
Html::footer();
?>