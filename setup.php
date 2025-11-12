<?php


use GlpiPlugin\Equipmentexit\Request as PluginEquipmentexitRequest;

define('PLUGIN_EQUIPMENTEXIT_VERSION', '3.8.0'); // v3.8
define("PLUGIN_EQUIPMENTEXIT_MIN_GLPI_VERSION", "10.0.0");

function plugin_init_equipmentexit() {
    global $PLUGIN_HOOKS, $DB;
    $plugin_name = 'equipmentexit';

    Plugin::registerClass(PluginEquipmentexitRequest::class);

    $PLUGIN_HOOKS['csrf_compliant'][$plugin_name] = true;
    
    // *** INÍCIO DA CORREÇÃO (v3.8) ***
    
    // 1. Menu do Solicitante (Helpdesk)
    $PLUGIN_HOOKS['menu_toadd'][$plugin_name]['knowbase'] = 'PluginEquipmentexitMenu';
    
    // 2. Menu do Aprovador (Plugins)
    //    Só aparece se o usuário for Admin OU estiver nas tabelas de papel
    if (PluginEquipmentexitMenu::canViewApprovalQueue($DB)) {
        $PLUGIN_HOOKS['menu_toadd'][$plugin_name]['plugins'] = 'PluginEquipmentexitApprovalMenu';
    }
    
    // 3. Menu de Configuração (Configuração > Geral)
    //    Só aparece para Super-Admins
    if (Session::haveRight('config', UPDATE)) {
         $PLUGIN_HOOKS['menu_toadd'][$plugin_name]['setup'] = 'PluginEquipmentexitConfigMenu';
    }
    // *** FIM DA CORREÇÃO ***
    
    $PLUGIN_HOOKS['add_css'][$plugin_name] = 'css/equipmentexit.css';
}

/**
 * Menu Padrão (Minhas Solicitações, Nova Solicitação)
 * Visto por Solicitantes (self-service) e Admins.
 */
class PluginEquipmentexitMenu {
    
    static function getMenuContent() {
        global $plugin_name;
        $plugin_name = 'equipmentexit';
        $links = [];
        
        $links['search'] = [ 
            'title' => __('Minhas Solicitações', $plugin_name),
            'page'  => '/plugins/equipmentexit/front/request.php',
            'icon'  => 'fas fa-list'
        ];
        
        $links['add'] = [ 
            'title' => __('Nova Solicitação', $plugin_name),
            'page'  => '/plugins/equipmentexit/front/request.form.php',
            'icon'  => 'fas fa-plus'
        ];
        
        // O link "Configurar" foi REMOVIDO daqui.

        return [
            'title'       => __('Saída de Equipamentos', $plugin_name), 
            'page'        => '/plugins/equipmentexit/front/request.php', 
            'icon'        => 'fas fa-sign-out-alt', 
            'links'       => $links,
        ];
    }

     static function getMenuName($nb = 0) {
         $content = self::getMenuContent();
         return $content['title'] ?? __('Saída de Equipamentos', 'equipmentexit');
     }
     
     /**
      * (v3.7) Função de verificação de permissão (SQL Bruto - Correta)
      */
     static function canViewApprovalQueue($DB) {
        if (Session::haveRight('config', UPDATE)) {
            return true;
        }
        $user_id = Session::getLoginUserID();
        if (empty($user_id)) {
            return false;
        }
        $tables_to_check = [
            'glpi_plugin_equipmentexit_gerentes',
            'glpi_plugin_equipmentexit_governanca',
            'glpi_plugin_equipmentexit_seguranca'
        ];
        foreach ($tables_to_check as $table_name) {
            $sql_check = "SELECT COUNT(*) as cpt FROM `$table_name` WHERE `users_id` = " . (int)$user_id;
            $check_result = $DB->query($sql_check);
            if ($check_result && ($count_row = $DB->fetchAssoc($check_result)) && $count_row['cpt'] > 0) {
                return true; 
            }
        }
        return false;
     }
}

/**
 * (v3.7) Menu da Fila de Ações
 * Visto por Aprovadores e Admins (no menu "Plugins").
 */
class PluginEquipmentexitApprovalMenu {
    static function getMenuContent() {
        global $plugin_name;
        $plugin_name = 'equipmentexit';
        $links = [];
        $links['approval_queue'] = [ 
            'title' => __('Fila de Ações', $plugin_name),
            'page'  => '/plugins/equipmentexit/front/approval.php',
            'icon'  => 'fas fa-tasks'
        ];
        return [
            'title'       => __('Fila de Ações', $plugin_name), 
            'page'        => '/plugins/equipmentexit/front/approval.php', 
            'icon'        => 'fas fa-tasks', 
            'links'       => $links,
        ];
    }
     static function getMenuName($nb = 0) {
         $content = self::getMenuContent();
         return $content['title'] ?? __('Fila de Ações', 'equipmentexit');
     }
}

/**
 * (v3.8) NOVO MENU DE CONFIGURAÇÃO
 * Visto apenas por Admins (no menu "Configuração").
 */
class PluginEquipmentexitConfigMenu {
    static function getMenuContent() {
        global $plugin_name;
        $plugin_name = 'equipmentexit';
        $links = [];

        // O link agora aponta para a página de configuração
        $links['config'] = [
            'title' => __('Saída de Equipamentos', $plugin_name), // Nome do plugin
            'page'  => '/plugins/equipmentexit/front/config.php',
            'icon'  => 'fas fa-sign-out-alt'
        ];

        return [
            // Este título não aparece, mas é necessário
            'title'       => __('Saída de Equipamentos', $plugin_name), 
            'page'        => '/plugins/equipmentexit/front/config.php',
            'links'       => $links,
            'icon'        => 'fas fa-sign-out-alt',
            'SUB_MENU'    => 'config' // Adiciona ao menu "Geral"
        ];
    }
    
     static function getMenuName($nb = 0) {
         return __('Saída de Equipamentos', 'equipmentexit');
     }
}


// --- Funções de Versão e Verificação (Inalteradas) ---
function plugin_version_equipmentexit() {
    return [
        'name'           => __('Saída de Equipamentos', 'equipmentexit'),
        'version'        => PLUGIN_EQUIPMENTEXIT_VERSION,
        'author'         => 'Kawan Costa', 
        'license'        => 'GPLv3', 
        'minGlpiVersion' => PLUGIN_EQUIPMENTEXIT_MIN_GLPI_VERSION
    ];
}
function plugin_equipmentexit_check_config($verbose = false) {
    return true;
}
function plugin_equipmentexit_check_prerequisites() {
    return true;
}

?>