<?php

namespace GlpiPlugin\Equipmentexit;

class MenuRequests {

    static function getMenuContent() {
        $plugin_name = 'equipmentexit';

        // Links internos (Minhas Requisições / Nova)
        $links = [];
        
        $links['my_requests'] = [
            'title' => __('Minhas Solicitações', 'equipmentexit'),
            'page'  => '/plugins/equipmentexit/front/request.php',
            'icon'  => 'fas fa-list'
        ];
        
        $links['new_request'] = [
            'title' => __('Nova Solicitação', 'equipmentexit'),
            'page'  => '/plugins/equipmentexit/front/request.form.php',
            'icon'  => 'fas fa-plus'
        ];

        return [
            'title'       => __('Saídas - Requisições', 'equipmentexit'),
            'page'        => '/plugins/equipmentexit/front/request.php',
            'icon'        => 'fas fa-list',
            'links'       => $links
        ];
    }

    static function getMenuName($nb = 0) {
         return __('Saídas - Requisições', 'equipmentexit');
    }
}