<?php
namespace GlpiPlugin\Equipmentexit;

use Session;

class Menu {
    static function getMenuContent() {
        global $plugin_name; // O GLPI injeta isso as vezes, mas é bom garantir
        $plugin_name = 'equipmentexit';
        
        $links = [];
        $links['search'] = [
            'title' => __('Minhas Solicitações', 'equipmentexit'),
            'page'  => '/plugins/equipmentexit/front/request.php',
            'icon'  => 'fas fa-list'
        ];
        $links['add'] = [
            'title' => __('Nova Solicitação', 'equipmentexit'),
            'page'  => '/plugins/equipmentexit/front/request.form.php',
            'icon'  => 'fas fa-plus'
        ];

        return [
            'title' => __('Saída de Equipamentos', 'equipmentexit'),
            'page'  => '/plugins/equipmentexit/front/request.php',
            'icon'  => 'fas fa-sign-out-alt',
            'links' => $links,
        ];
    }

    static function getMenuName($nb = 0) {
         return __('Saída de Equipamentos', 'equipmentexit');
    }
}