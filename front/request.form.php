<?php

include ('../../../inc/includes.php');

// *** MUDANÇA: Autoloading ativo, apenas definimos o namespace ***
use GlpiPlugin\Equipmentexit\Request as PluginEquipmentexitRequest;

global $DB;
$plugin_name = 'equipmentexit';

if (!PluginEquipmentexitRequest::canCreate()) {
    Html::header(__('Nova Solicitação', $plugin_name), $_SERVER['PHP_SELF'], 'plugins', $plugin_name);
    Html::displayErrorAndDie(__('Acesso negado. Você não tem autorização para criar solicitações.', $plugin_name));
}

$item = new PluginEquipmentexitRequest();

if (isset($_POST["add"])) {
    if ($newID = $item->add($_POST)) {
        Session::addMessageAfterRedirect(__('Item adicionado com sucesso', 'equipmentexit'), true, INFO);
        if (isset($_POST['_add_another']) && $_POST['_add_another'] == 1) {
             Html::redirect($item->getFormURL());
        } else {
             Html::redirect($item->getFormURL(['id' => $newID]));
        }
    } else {
        Html::back();
    }

} else if (isset($_POST["update"])) {
    if ($item->update($_POST)) {
        Session::addMessageAfterRedirect(__('Item atualizado com sucesso', 'equipmentexit'), true, INFO);
        Html::back(); 
    } else {
         Session::addMessageAfterRedirect(__('Falha ao atualizar o item', 'equipmentexit'), true, ERROR);
         Html::back(); 
    }

} else if (isset($_POST["delete"])) {
    if ($item->delete($_POST)) {
        Session::addMessageAfterRedirect(__('Item movido para a lixeira com sucesso', 'equipmentexit'), true, INFO);
        $item->redirectToList(); 
    } else {
        Session::addMessageAfterRedirect(__('Falha ao mover o item para a lixeira', 'equipmentexit'), true, ERROR);
        Html::back(); 
    }

} else if (isset($_POST["restore"])) {
    if ($item->restore($_POST)) {
        Session::addMessageAfterRedirect(__('Item restaurado com sucesso', 'equipmentexit'), true, INFO);
        $item->redirectToList(); 
    } else {
        Session::addMessageAfterRedirect(__('Falha ao restaurar o item', 'equipmentexit'), true, ERROR);
         Html::back();
    }

} else if (isset($_POST["purge"])) {
    if ($item->delete($_POST, 1)) {
        Session::addMessageAfterRedirect(__('Item excluído permanentemente com sucesso', 'equipmentexit'), true, INFO);
        $item->redirectToList(); 
    } else {
        Session::addMessageAfterRedirect(__('Falha ao excluir o item permanentemente', 'equipmentexit'), true, ERROR);
        Html::back(); 
    }

} else {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    Html::header(
        PluginEquipmentexitRequest::getTypeName(), 
        $_SERVER['PHP_SELF'],                      
        'knowbase', 
        'PluginEquipmentexitRequest',              
        'equipmentexit'               
    );
    
    $options = ['id' => $id, 'target' => $item->getFormURL()];

    if ($id == 0) {
        $item->showForm(0, $options);
    } else {
        $item->display($options);
    }

    Html::footer();
}
?>