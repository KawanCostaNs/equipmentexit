<?php

function plugin_equipmentexit_install() {
    global $DB; 

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $engine            = 'ENGINE=InnoDB';

    // --- Tabela 1: Solicita莽玫es (requests) - Cabe莽alho (v3.0) ---
    $table_requests = 'glpi_plugin_equipmentexit_requests';
    if (!$DB->tableExists($table_requests)) {
        $query_requests = "CREATE TABLE `$table_requests` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `is_deleted` TINYINT NOT NULL DEFAULT '0',
            `name` VARCHAR(255) COLLATE $default_collation NULL,
            `users_id_requester` INT UNSIGNED NOT NULL,
            `reason` TEXT COLLATE $default_collation,
            `status` INT NOT NULL DEFAULT '1',
            `date_request` DATETIME NULL,
            `date_exit_planned` DATETIME NULL,
            `date_return_planned` DATETIME NULL,
            `local_origem` VARCHAR(255) COLLATE $default_collation NULL,
            `tipo_movimentacao` VARCHAR(100) COLLATE $default_collation NULL,
            `users_id_gerente` INT UNSIGNED NULL,
            `date_gerente` DATETIME NULL,
            `comment_gerente` TEXT COLLATE $default_collation,
            `users_id_governanca` INT UNSIGNED NULL,
            `date_governanca` DATETIME NULL,
            `comment_governanca` TEXT COLLATE $default_collation,
            `users_id_seg_saida` INT UNSIGNED NULL,
            `date_seg_saida` DATETIME NULL,
            `comment_seg_saida` TEXT COLLATE $default_collation,
            `users_id_seg_chegada` INT UNSIGNED NULL,
            `date_seg_chegada` DATETIME NULL,
            `comment_seg_chegada` TEXT COLLATE $default_collation,
            `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `date_mod` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `users_id_requester` (`users_id_requester`),
            KEY `status` (`status`)
        ) $engine DEFAULT CHARSET=$default_charset COLLATE=$default_collation";
        $DB->queryOrDie($query_requests, $DB->error());
    }

    // --- TABELA 2: Itens da Solicita莽茫o (v3.2) ---
    $table_request_items = 'glpi_plugin_equipmentexit_request_items';
    if (!$DB->tableExists($table_request_items)) {
        $query_items = "CREATE TABLE `$table_request_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `plugin_equipmentexit_requests_id` INT UNSIGNED NOT NULL, 
            `tickets_id` INT UNSIGNED NULL,
            `equipamento_nome` VARCHAR(255) COLLATE $default_collation NULL,
            `equipamento_tipo` VARCHAR(255) COLLATE $default_collation NULL, /* <-- NOVA COLUNA v3.2 */
            `quantidade` INT NOT NULL DEFAULT '1',
            `patrimonio` VARCHAR(255) COLLATE $default_collation NULL,
            `loja_destino` VARCHAR(255) COLLATE $default_collation NULL,
            
            PRIMARY KEY (`id`),
            KEY `request_id` (`plugin_equipmentexit_requests_id`)
        ) $engine DEFAULT CHARSET=$default_charset COLLATE=$default_collation";
        $DB->queryOrDie($query_items, $DB->error());
    }

    // --- Tabela 3: Solicitantes (requesters) ---
    $table_requesters = 'glpi_plugin_equipmentexit_requesters';
    if (!$DB->tableExists($table_requesters)) {
        $query_requesters = "CREATE TABLE `$table_requesters` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `users_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_id` (`users_id`)
        ) $engine DEFAULT CHARSET=$default_charset COLLATE=$default_collation";
        $DB->queryOrDie($query_requesters, $DB->error());
    }

    // --- Tabela 4: Gerentes ---
    $table_gerentes = 'glpi_plugin_equipmentexit_gerentes';
    if (!$DB->tableExists($table_gerentes)) {
        $query_gerentes = "CREATE TABLE `$table_gerentes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `users_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_id` (`users_id`)
        ) $engine DEFAULT CHARSET=$default_charset COLLATE=$default_collation";
        $DB->queryOrDie($query_gerentes, $DB->error());
    }

    // --- Tabela 5: Governan莽a ---
    $table_governanca = 'glpi_plugin_equipmentexit_governanca';
    if (!$DB->tableExists($table_governanca)) {
        $query_governanca = "CREATE TABLE `$table_governanca` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `users_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_id` (`users_id`)
        ) $engine DEFAULT CHARSET=$default_charset COLLATE=$default_collation";
        $DB->queryOrDie($query_governanca, $DB->error());
    }

    // --- Tabela 6: Seguran莽a ---
    $table_seguranca = 'glpi_plugin_equipmentexit_seguranca';
    if (!$DB->tableExists($table_seguranca)) {
        // *** MUDANÇA AQUI ***
        $query_seguranca = "CREATE TABLE `$table_seguranca` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `users_id` INT UNSIGNED NOT NULL,
            `loja` VARCHAR(255) COLLATE $default_collation NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_id_loja` (`users_id`, `loja`(191))
        ) $engine DEFAULT CHARSET=$default_charset COLLATE=$default_collation";
        // *** FIM DA MUDANÇA ***
        $DB->queryOrDie($query_seguranca, $DB->error());
    }

    return true; 
}

/**
 * Fun莽茫o de desinstala莽茫o do plugin.
 * ATUALIZADO (v3.0): Remove a nova tabela 'items'
 */
function plugin_equipmentexit_uninstall() {
    global $DB;
    
    $tables = [
        'glpi_plugin_equipmentexit_requests',
        'glpi_plugin_equipmentexit_request_items', // Tabela de Itens
        'glpi_plugin_equipmentexit_requesters',
        'glpi_plugin_equipmentexit_gerentes',
        'glpi_plugin_equipmentexit_governanca',
         'glpi_plugin_equipmentexit_seguranca',
        
        // Tabelas antigas (v1.0)
        'glpi_plugin_equipmentexit_approvals',
        'glpi_plugin_equipmentexit_approvers'
    ];
    
    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->queryOrDie("DROP TABLE `$table`", $DB->error());
        }
    }

    return true;
}

?>