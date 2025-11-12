<?php


include ('../../../inc/includes.php');
// Inclui o núcleo do GLPI

// Inclui manualmente o arquivo da classe Request
require_once(__DIR__ . '/../inc/Request.php');
use GlpiPlugin\Equipmentexit\Request as PluginEquipmentexitRequest;

global $DB;
$plugin_name = 'equipmentexit';

// --- Verificação de Permissão (Usa a função da classe) ---
if (!PluginEquipmentexitRequest::canCreate()) {
    Html::header(__('Nova Solicitação', $plugin_name), $_SERVER['PHP_SELF'], 'plugins', $plugin_name);
    Html::displayErrorAndDie(__('Acesso negado. Você não tem autorização para criar solicitações.', $plugin_name));
}
// Se for Super-Admin ou estiver na lista, o script continua...


// Instancia o objeto principal
$item = new PluginEquipmentexitRequest();

// --- Processamento de Ações POST ---

// Ação: Adicionar novo item
if (isset($_POST["add"])) {
    // REMOVIDO: // Session::check...

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

// Ação: Atualizar item existente
} else if (isset($_POST["update"])) {
    // REMOVIDO: // Session::check...

    if ($item->update($_POST)) {
        Session::addMessageAfterRedirect(__('Item atualizado com sucesso', 'equipmentexit'), true, INFO);
        Html::back(); 
    } else {
         Session::addMessageAfterRedirect(__('Falha ao atualizar o item', 'equipmentexit'), true, ERROR);
         Html::back(); 
    }


// Ação: Mover para a lixeira
} else if (isset($_POST["delete"])) {
    // REMOVIDO: // Session::check...

    if ($item->delete($_POST)) {
        Session::addMessageAfterRedirect(__('Item movido para a lixeira com sucesso', 'equipmentexit'), true, INFO);
        $item->redirectToList(); 
    } else {
        Session::addMessageAfterRedirect(__('Falha ao mover o item para a lixeira', 'equipmentexit'), true, ERROR);
        Html::back(); 
    }


// Ação: Restaurar da lixeira
} else if (isset($_POST["restore"])) {
    // REMOVIDO: // Session::check...

    if ($item->restore($_POST)) {
        Session::addMessageAfterRedirect(__('Item restaurado com sucesso', 'equipmentexit'), true, INFO);
        $item->redirectToList(); 
    } else {
        Session::addMessageAfterRedirect(__('Falha ao restaurar o item', 'equipmentexit'), true, ERROR);
         Html::back();
    }


// Ação: Apagar permanentemente
} else if (isset($_POST["purge"])) {
    // REMOVIDO: // Session::check...

    if ($item->delete($_POST, 1)) {
        Session::addMessageAfterRedirect(__('Item excluído permanentemente com sucesso', 'equipmentexit'), true, INFO);
        $item->redirectToList(); 
    } else {
        Session::addMessageAfterRedirect(__('Falha ao excluir o item permanentemente', 'equipmentexit'), true, ERROR);
        Html::back(); 
    }


// Nenhuma ação POST: Exibir o formulário
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

    // *** INÍCIO DA CORREÇÃO (v3.1) ***
    // Restaura a lógica para contornar o bug de abas em item novo
    if ($id == 0) {
        // Se for NOVO (ID=0), chama apenas showForm()
        $item->showForm(0, $options);
    } else {
        // Se for um item existente (ID > 0), chama display()
        $item->display($options);
    }
    // *** FIM DA CORREÇÃO ***

    Html::footer();
}

?>