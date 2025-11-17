<?php
namespace GlpiPlugin\Equipmentexit;

class MenuApprovals {
    static function getMenuContent() {
        return [
            'title' => __('Saídas - Aprovações', 'equipmentexit'),
            'page'  => '/plugins/equipmentexit/front/approval.php',
            'icon'  => 'fas fa-tasks',
            'links' => []
        ];
    }

    static function getMenuName($nb = 0) {
         return __('Saídas - Aprovações', 'equipmentexit');
    }
}