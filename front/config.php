<?php


include ('../../../inc/includes.php');
// Inclui a classe Request para usar a lista de lojas
require_once(__DIR__ . '/../inc/Request.php');
use GlpiPlugin\Equipmentexit\Request as PluginEquipmentexitRequest;


if (!Session::haveRight('config', UPDATE)) {
    Html::displayRightError();
}

global $DB; 
$plugin_name = 'equipmentexit';
$redirect_url = $CFG_GLPI['root_doc'] . "/plugins/$plugin_name/front/config.php";

// --- Processamento de Ações ---
if (isset($_POST["add_gerente"]) || isset($_POST["add_governanca"]) || isset($_POST["add_seguranca"]) || isset($_POST["add_requester"])) {
    // REMOVIDO: CSRF Check
}

// *** INÍCIO DA ADIÇÃO: Processamento do Upload do Logo ***
if (isset($_POST["upload_logo"])) {
    // REMOVIDO: CSRF Check (para manter consistência com o arquivo)
    
    $target_dir = __DIR__ . '/../images/';
    $target_file = $target_dir . 'custom_logo.png'; // Nome fixo para o novo logo
    $uploadOk = 1;
    
    if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] == 0) {
        $imageFileType = strtolower(pathinfo($_FILES['logo_upload']['name'], PATHINFO_EXTENSION));
        
        // Verifica se é uma imagem
        $check = @getimagesize($_FILES['logo_upload']['tmp_name']);
        
        if($check === false) {
            Session::addMessageAfterRedirect(__('Arquivo não é uma imagem válida.', $plugin_name), true, ERROR);
            $uploadOk = 0;
        }
        
        // Permite apenas PNG
        if($imageFileType != "png") {
            Session::addMessageAfterRedirect(__('Desculpe, apenas arquivos PNG são permitidos.', $plugin_name), true, ERROR);
            $uploadOk = 0;
        }

        if ($uploadOk != 0) {
            // Tenta mover o arquivo
            if (move_uploaded_file($_FILES['logo_upload']['tmp_name'], $target_file)) {
                Session::addMessageAfterRedirect(__('O novo logo foi salvo com sucesso.', $plugin_name), true, INFO);
            } else {
                Session::addMessageAfterRedirect(__('Desculpe, houve um erro ao salvar seu arquivo.', $plugin_name), true, ERROR);
            }
        }
    } else {
         Session::addMessageAfterRedirect(__('Nenhum arquivo enviado ou erro no upload.', $plugin_name), true, WARNING);
    }
    Html::redirect($redirect_url);
}
// *** FIM DA ADIÇÃO ***

function addUserToRole($table_name, $post_user_id, $role_name, $loja = null) {
    global $DB, $redirect_url, $plugin_name;
    $user_id_to_add = (int)$_POST[$post_user_id];
    if ($user_id_to_add <= 0) {
        Html::redirect($redirect_url);
        return;
    }
    
    // Constrói os dados de inserção
    $insert_data = ['users_id' => $user_id_to_add];
    $role_name_display = $role_name;

    // *** INÍCIO DA CORREÇÃO (Gemini): Reverte $DB->count para $DB->query ***
    // Monta a query de verificação
    $sql_check = "SELECT COUNT(*) as cpt FROM `$table_name` WHERE `users_id` = " . (int)$user_id_to_add;

    if ($loja !== null) {
        if (empty($loja)) {
            Session::addMessageAfterRedirect(__('Você deve selecionar uma loja para o Segurança.', $plugin_name), true, WARNING);
            Html::redirect($redirect_url);
            return;
        }
        $insert_data['loja'] = $loja;
        $sql_check .= " AND `loja` = '" . $DB->escape($loja) . "'"; // Adiciona loja na verificação
        $role_name_display = "$role_name (Loja: $loja)";
    }

    // Executa a verificação
    $check_result = $DB->query($sql_check);
    $existing_count = 0;
    if ($check_result && ($count_row = $DB->fetchAssoc($check_result))) {
        $existing_count = $count_row['cpt'];
    }
    // *** FIM DA CORREÇÃO ***
    
     if ($existing_count == 0) {
        $DB->insert($table_name, $insert_data); // $insert_data já contém 'loja' se necessário
        Session::addMessageAfterRedirect(sprintf(__('%s adicionado com sucesso!', $plugin_name), $role_name_display), true, INFO);
    } else {
        Session::addMessageAfterRedirect(sprintf(__('Este usuário já está na lista de %s.', $plugin_name), strtolower($role_name_display)), true, WARNING);
    }
    Html::redirect($redirect_url);
}

function removeUserFromRole($table_name, $get_id, $role_name) {
    global $DB, $redirect_url, $plugin_name;
    if (isset($_GET[$get_id]) && $_GET[$get_id] > 0) {
         $row_id_to_delete = (int)$_GET[$get_id];
        $DB->delete($table_name, ['id' => $row_id_to_delete]);
        Session::addMessageAfterRedirect(sprintf(__('%s removido com sucesso!', $plugin_name), $role_name), true, INFO);
        Html::redirect($redirect_url);
    }
}

// Processar Ações
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

// --- Busca de Dados ---
function getUsersInRole($table_name) {
    global $DB;

    $loja_select = "";
    $loja_order_by = ""; 

    if ($table_name == 'glpi_plugin_equipmentexit_seguranca') {
        $loja_select = ", role_table.loja"; 
        $loja_order_by = ", role_table.loja ASC"; 
    }

    $sql_query = "SELECT role_table.id AS row_id, users.id AS users_id, users.name AS username, users.realname, users.firstname $loja_select
                  FROM `$table_name` AS role_table
                  INNER JOIN `glpi_users` AS users ON (role_table.users_id = users.id)
                  ORDER BY users.name ASC $loja_order_by"; 
    
    $query_result = $DB->query($sql_query);
    
    if (!$query_result) {
        // Log do erro real
        Log::logError("Falha na consulta SQL em config.php: " . $DB->error());
        // Mostra um erro amigável
        Html::displayErrorAndDie("Erro ao buscar usuários. Verifique os logs do GLPI.");
    }
    
     return iterator_to_array($query_result);
}


$current_gerentes    = getUsersInRole('glpi_plugin_equipmentexit_gerentes');
$current_governanca  = getUsersInRole('glpi_plugin_equipmentexit_governanca');
$current_seguranca   = getUsersInRole('glpi_plugin_equipmentexit_seguranca');
$current_requesters  = getUsersInRole('glpi_plugin_equipmentexit_requesters');


// --- Exibição da Página ---
Html::header(__('Configuração - Saída de Equipamentos', $plugin_name), $_SERVER['PHP_SELF'], 'setup', $plugin_name);

echo "<div class='center-layout'><div class='glpi_form spaced'>";
$csrf_token = Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

// *** INÍCIO DA ADIÇÃO: Card de Upload do Logo ***
echo "<div class='role-management-card'>"; 
echo "<div class='role-card-header'>";
echo "<h2><i class='fas fa-image'></i> " . __('Logo do PDF', $plugin_name) . "</h2>";
echo "<p>" . __('Faça o upload de um novo logo (formato PNG) para ser usado nos Termos de Movimentação em PDF. O logo atual será substituído.', $plugin_name) . "</p>";
echo "</div>"; 
echo "<div class='role-card-footer'>"; // Reutilizando a classe do rodapé para o form
echo "<form method='post' action='$redirect_url' enctype='multipart/form-data' class='add-user-form'>";
echo $csrf_token;
echo "<div class='form-input' style='flex-grow: 3;'>"; // Mais espaço para o input
echo "<strong>" . __('Selecionar logo (apenas .PNG)', $plugin_name) . "</strong><br>";
echo "<input type='file' name='logo_upload' accept='image/png' class='glpi_input' style='width: 100%; height: auto; padding: 5px;'>";
echo "</div>";
echo "<div class='form-action'>";
echo "<input type='submit' name='upload_logo' value='" . __('Enviar Logo', $plugin_name) . "' class='submit'>";
echo "</div>";
Html::closeForm();
echo "</div>"; 
echo "</div>"; 
// *** FIM DA ADIÇÃO ***


function displayRoleSection($title, $description, $current_users_list, $delete_param_name, $form_user_name, $form_submit_name, $form_name = '') {
    
    global $redirect_url, $plugin_name, $csrf_token;
    
    echo "<div class='role-management-card'>"; 
    
    // Cabeçalho do Card
    echo "<div class='role-card-header'>";
    echo "<h2>" . $title . "</h2>";
    echo "<p>" . $description . "</p>";
    echo "</div>"; 

    // Corpo do Card (Lista de Usuários)
    echo "<div class='role-card-body'>";
    echo "<ul class='role-user-list'>";
    
    if (count($current_users_list) > 0) {
        foreach ($current_users_list as $user) {
            $delete_link = $redirect_url . "?" . $delete_param_name . "=" . $user['row_id'];
            
            $user_display_name = Html::clean($user['firstname'] . " " . $user['realname']);
            
            $loja_display = '';
            if (isset($user['loja']) && !empty($user['loja'])) {
                 $loja_display = " <strong style='color: #555;'>(" . Html::clean($user['loja']) . ")</strong>";
            }

            echo "<li class='role-user-item'>";
            echo "<div class='user-info'>";
            echo "<span class='user-name'>" . $user_display_name . $loja_display . "</span>"; // Loja adicionada aqui
            echo "<span class='user-login'>(" . $user['username'] . ")</span>";
            echo "</div>";
            echo "<div class='user-actions'>";
            echo "<a href='$delete_link' class='btn btn-danger-outline' onclick=\"return confirm('" . addslashes(__('Tem certeza que deseja remover este usuário?', $plugin_name)) . "');\">";
            echo "<i class='fas fa-trash-alt'></i> " . __('Remover');
            echo "</a>";
            echo "</div>";
            echo "</li>";
        }
    } else {
        echo "<li class='role-user-item-empty'>" . __('Nenhum usuário configurado para este papel.', $plugin_name) . "</li>";
    }
    echo "</ul>";
    echo "</div>"; 

    // Rodapé do Card (Formulário de Adição)
    echo "<div class='role-card-footer'>";
    echo "<form method='post' action='$redirect_url' class='add-user-form'>";
    echo $csrf_token;
    
    echo "<div class='form-input'>";
    echo "<strong>" . __('Adicionar Novo Usuário', $plugin_name) . "</strong><br>";
    User::dropdown(['name'  => $form_user_name, 'value' => 0, 'right' => 'all', 'width' => '100%']);
    echo "</div>";
    
    if ($form_name == 'seguranca') {
        echo "<div class='form-input'>";
        echo "<strong>" . __('Loja', 'equipmentexit') . "</strong><br>";
        
        $stores = PluginEquipmentexitRequest::getStoreList();
        echo "<select name='loja_seguranca' class='glpi_input' style='width: 100%;'>";
        echo "<option value=''>" . __('-- Selecione uma loja --') . "</option>";
        foreach ($stores as $store) {
            echo "<option value=\"" . Html::clean($store) . "\">" . Html::clean($store) . "</option>";
        }
        echo "</select>";
        echo "</div>";
    }

    echo "<div class='form-action'>";
    echo "<input type='submit' name='$form_submit_name' value='" . __('Adicionar', $plugin_name) . "' class='submit'>";
    echo "</div>";
    
    Html::closeForm();
    echo "</div>"; 
    
    echo "</div>"; 
}

// Renderiza as seções
displayRoleSection(
    "<i class='fas fa-user-tie'></i> " . __('Gerentes', $plugin_name),
    __('Usuários que podem aprovar a Etapa 1 (Aprovação Gerencial).', $plugin_name),
    $current_gerentes, 'delete_gerente_id', 'users_id_gerente', 'add_gerente'
);
displayRoleSection(
    "<i class='fas fa-user-shield'></i> " . __('Governança', $plugin_name),
    __('Usuários que podem aprovar a Etapa 2 (Aprovação de Governança).', $plugin_name),
     $current_governanca, 'delete_governanca_id', 'users_id_governanca', 'add_governanca'
);
displayRoleSection(
    "<i class='fas fa-hard-hat'></i> " . __('Segurança Patrimonial', $plugin_name),
    __('Usuários que podem validar as Etapas 3 (Saída) e 4 (Chegada) para suas lojas específicas.', $plugin_name),
    $current_seguranca, 'delete_seguranca_id', 'users_id_seguranca', 'add_seguranca', 'seguranca'
);
displayRoleSection(
    "<i class='fas fa-user-check'></i> " . __('Solicitantes Autorizados', $plugin_name),
    __('Usuários que podem criar novas solicitações de saída.', $plugin_name),
    $current_requesters, 'delete_requester_id', 'users_id_requester', 'add_requester'
);

echo "</div></div>";
Html::footer();
?>