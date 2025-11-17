<?php
namespace GlpiPlugin\Equipmentexit;

use CommonDBTM;
use Html;
use Session;
use User;
use Notepad;
use Log;
use Location; 

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class Request extends CommonDBTM {

    static $table = 'glpi_plugin_equipmentexit_requests';
    static $pk_field = 'id';
    static public $rightname = 'all';
    public $dohistory = true;

    static $item_table = 'glpi_plugin_equipmentexit_request_items';
    static $fk_field = 'plugin_equipmentexit_requests_id';

    static function getStoreList() {
        global $DB;
        $locations = []; 
        $location_table = Location::getTable(); 

        $iterator = $DB->request([
            'SELECT' => 'name',
            'FROM'   => $location_table,
            'ORDER'  => 'name ASC'
        ]);

        foreach ($iterator as $row) {
            $locations[] = $row['name'];
        }

        return $locations;
    }
    
    static function getTypeName($nb = 0) {
        return _n('Solicitação de Saída', 'Solicitações de Saída', $nb, 'equipmentexit');
    }

    function getItems() {
        global $DB;
        $items = []; 
        if (!$this->getID()) {
            return $items;
        }
        $iterator = $DB->request([
            'SELECT' => '*',
            'FROM'   => self::$item_table,
            'WHERE'  => [self::$fk_field => $this->getID()]
        ]);
        foreach ($iterator as $item_data) {
            $items[] = $item_data;
        }
        return $items;
    }

    function rawSearchOptions() {
        $options = []; 
        $options[] = ['id' => 'common', 'name' => __('Características')]; 
        $options[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'id', 'name'  => __('ID'), 'datatype' => 'number', 'display'  => true]; 
        $options[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'status', 'name'  => __('Status'), 'datatype' => 'specific', 'display'  => true]; 
        $options[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'id', 'name'  => __('Item(ns)', 'equipmentexit'), 'datatype'  => 'specific', 'display'   => true]; 
        $options[] = [
            'id'    => 4, 
            'table' => 'glpi_users', 
            'field' => 'name', 
            'name'  => __('Solicitante', 'equipmentexit'), 
            'datatype' => 'dropdown', 
            'display'  => true,
            'joinparams' => ['jointype' => 'inner', 'on' => ['table' => self::getTable(), 'field' => 'users_id_requester', 'eqfield' => 'id']]
        ];
        $options[] = ['id' => 5, 'table' => self::getTable(), 'field' => 'date_request', 'name'  => __('Data da Solicitação', 'equipmentexit'), 'datatype' => 'datetime', 'display'  => true]; 
        $options[] = ['id' => 8, 'table' => self::getTable(), 'field' => 'local_origem', 'name'  => __('Origem', 'equipmentexit'), 'datatype' => 'text', 'display'  => true]; 
        $options[] = ['id' => 80, 'table' => self::getTable(), 'field' => 'date_mod', 'name'  => __('Última atualização'), 'datatype' => 'datetime', 'display'  => false]; 
        return $options;
    }
    
    static function getSpecificValueToDisplay($field, $values, array $options = []) { 
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'status':
                return self::getStatusName($values['status']); 
            case 'id': 
                return __('(Múltiplos Itens)', 'equipmentexit');
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }
    
    static function getStatusName($status) {
        switch ($status) {
            case 1: return __('Pendente Gerente', 'equipmentexit');
            case 2: return __('Pendente Governança', 'equipmentexit');
            case 3: return __('Pendente Segurança Saída', 'equipmentexit');
            case 4: return __('Em Trânsito (Pendente Chegada)', 'equipmentexit');
            case 5: return __('Concluído', 'equipmentexit');
            case 9: return __('Rejeitado', 'equipmentexit');
            default: return __('Desconhecido', 'equipmentexit');
        }
    }

    static function canCreate($options = []): bool { 
         global $DB;
        if (Session::haveRight('config', UPDATE)) {
            return true;
        }
        $user_id = Session::getLoginUserID();
        if (empty($user_id)) {
            return false;
        }
        
        $table_name = 'glpi_plugin_equipmentexit_requesters';
        if (!$DB->tableExists($table_name)) {
            return false;
        }

        $iterator = $DB->request([
            'FROM' => $table_name,
            'WHERE' => ['users_id' => $user_id]
        ]);
        
        return count($iterator) > 0;
    }
    
    function defineTabs($options = []) { 
        $tabs = []; 
        $this->addDefaultFormTab($tabs);
        if ($this->getID()) {
            $this->addStandardTab(Notepad::class, $tabs, $options);
            $this->addStandardTab(Log::class, $tabs, $options);
        }
        return $tabs;
    }

    private function displayStoreDropdown($name, $selected = '') {
        $stores = self::getStoreList();
        echo "<select name='$name' class='glpi_input'>";
        echo "<option value=''>" . __('-- Selecione uma loja --') . "</option>";
        foreach ($stores as $store) {
            $is_selected = ($store == $selected) ? 'selected' : '';
            // *** CORREÇÃO: htmlspecialchars ao invés de Html::entities ***
            $safe_store = htmlspecialchars($store, ENT_QUOTES);
            echo "<option value=\"$safe_store\" $is_selected>$safe_store</option>";
        }
        echo "</select>";
    }

    function showForm($ID, array $options = []) { 
        global $CFG_GLPI, $DB;
        $this->initForm($ID, $options);

        $existing_items = ($ID > 0) ? $this->getItems() : []; 

        echo "<div class='equipment-form-container'>";
        // *** CORREÇÃO: htmlspecialchars aqui também ***
        echo "<form method='post' action='" . htmlspecialchars($options['target'], ENT_QUOTES) . "'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); 
        
        echo "<div class='equipment-form-card'>";
        echo "<table class='tab_cadre_fixe glpi_form'>";
        
        if ($ID > 0) {
             echo "<tr class='header headerRow'><th colspan='4'>" .
                  sprintf(__('Solicitação de Saída #%d'), $ID) . "</th></tr>";
        }

        echo "<tr><td colspan='4' class='section-header-cell'>";
        echo "<h2 class='form-section-title'><i class='fas fa-map-marker-alt'></i> " . __('1. Local de Origem') . "</h2>";
        echo "</td></tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td style='width: 20%;'>" . __('Local de Origem', 'equipmentexit') . " <span class='required'>*</span></td>";
        echo "<td colspan='3'>";
        $this->displayStoreDropdown('local_origem', $this->fields['local_origem'] ?? '');
        echo "</td>";
        echo "</tr>";

        echo "<tr><td colspan='4' class='section-header-cell'>";
        echo "<h2 class='form-section-title'><i class='fas fa-truck'></i> " . __('2. Informações da Movimentação') . "</h2>";
        echo "</td></tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Tipo de Movimentação', 'equipmentexit') . " <span class='required'>*</span></td>";
        echo "<td colspan='3'>";
        $tipos = [ 
            'corporativo' => __('Saída para Departamento Corporativo', 'equipmentexit'),
            'loja'        => __('Saída para Loja', 'equipmentexit'),
            'cd'          => __('Saída para Centro de Distribuição (CD)', 'equipmentexit'),
        ];
        $this->displayRadioButtons('tipo_movimentacao', $tipos, $this->fields['tipo_movimentacao'] ?? 'corporativo');
        echo "</td>";
        echo "</tr>";
        
        echo "<tr><td colspan='4' class='section-header-cell'>";
        echo "<h2 class='form-section-title'><i class='fas fa-laptop'></i> " . __('3. Identificação dos Equipamentos') . "</h2>";
        echo "</td></tr>";
        echo "<tr class='tab_bg_1'><td colspan='4' style='padding: 10px 20px;'>";
        echo "<div id='equipment_items_container'>";
        if (count($existing_items) > 0) {
            foreach ($existing_items as $item) {
                $this->displayItemBox($item);
            }
        } else {
            $this->displayItemBox();
        }
        echo "</div>"; 

        echo "<button type='button' id='add_item_button' class='btn-action-primary add-item-btn-styled'>";
        echo "<i class='fas fa-plus'></i> " . __('Adicionar outro item', 'equipmentexit');
        echo "</button>";

        echo "</td></tr>";
        
        echo "<tr><td colspan='4' class='section-header-cell'>";
        echo "<h2 class='form-section-title'><i class='fas fa-align-left'></i> " . __('4. Detalhes Adicionais') . "</h2>";
        echo "</td></tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Justificativa', 'equipmentexit') . " <span class='required'>*</span></td>";
        echo "<td colspan='3'><textarea name='reason' rows='4' class='glpi_input'>" . ($this->fields['reason'] ?? '') . "</textarea></td>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Data Prevista Saída', 'equipmentexit') . " <span class='required'>*</span></td>";
        echo "<td>";
        Html::showDateTimeField('date_exit_planned', [
            'value'      => $this->fields['date_exit_planned'] ?? date('Y-m-d H:i:s'),
            'maybeempty' => false
        ]);
        echo "</td>";
        echo "<td>" . __('Data Prevista Retorno', 'equipmentexit') . "</td>";
        echo "<td>";
        Html::showDateTimeField('date_return_planned', [
            'value'      => $this->fields['date_return_planned'] ?? '',
            'maybeempty' => true
        ]);
        echo "</td>";
        echo "</tr>";

        echo Html::hidden('id', ['value' => $this->fields['id']]); 
        if ($ID > 0) {
             echo Html::hidden('users_id_requester', ['value' => $this->fields['users_id_requester']]); 
        }
        if ($ID == 0) {
            echo Html::hidden('date_request', ['value' => date('Y-m-d H:i:s')]); 
        }

        $this->showFormButtons($options);
        echo "</table>";
        echo "</div>"; 
        echo "</form>";
        echo "</div>"; 

        $this->displayItemBoxScript();
        return true;
    }
    
    private function displayItemBox($item_data = null) {
        $tickets_id = $item_data['tickets_id'] ?? '';
        $equip_nome = $item_data['equipamento_nome'] ?? '';
        $equip_tipo = $item_data['equipamento_tipo'] ?? ''; 
        $qtd        = $item_data['quantidade'] ?? '1';
        $patrimonio = $item_data['patrimonio'] ?? '';
        $destino    = $item_data['loja_destino'] ?? '';
        
        echo "<div class='item-box'>"; 
        echo "<div class='item-box-header'>";
        echo "<button type='button' class='btn-icon remove-item-btn' title='" . __('Remover Item') . "'><i class='fas fa-times'></i></button>";
        echo "</div>";
        echo "<div class='item-box-body'>";
        echo "<div class='form-field'>";
        echo "<label>" . __('Nº do Chamado', 'equipmentexit') . "</label>";
        echo "<input type='number' name='tickets_id[]' value='$tickets_id' class='glpi_input'>";
        echo "</div>";
        echo "<div class'form-field'>";
        echo "<label>" . __('Nº do Patrimônio', 'equipmentexit') . "</label>";
        echo "<input type='text' name='patrimonio[]' value='$patrimonio' class='glpi_input'>";
        echo "</div>";
        echo "<div class='form-field'>";
        echo "<label>" . __('Nome do Equipamento', 'equipmentexit') . " <span class='required'>*</span></label>";
        echo "<input type='text' name='equipamento_nome[]' value='$equip_nome' class='glpi_input'>";
        echo "</div>";
        echo "<div class='form-field'>";
        echo "<label>" . __('Tipo do Equipamento', 'equipmentexit') . " <span class='required'>*</span></label>";
        echo "<input type='text' name='equipamento_tipo[]' value='$equip_tipo' class='glpi_input'>";
        echo "</div>";
        echo "<div class='form-field'>";
        echo "<label>" . __('Quantidade', 'equipmentexit') . " <span class='required'>*</span></label>";
        echo "<input type='number' name='quantidade[]' value='$qtd' class='glpi_input' style='max-width: 120px;'>";
        echo "</div>";
        echo "<div class='form-field'>";
        echo "<label>" . __('Loja de Destino', 'equipmentexit') . " <span class='required'>*</span></label>";
        $this->displayStoreDropdown('loja_destino[]', $destino);
        echo "</div>";
        echo "</div>"; 
        echo "</div>"; 
    }
  
    private function displayItemBoxScript() {
        ob_start();
        $this->displayStoreDropdown('loja_destino[]');
        $store_dropdown_html = ob_get_clean();
        $store_dropdown_html = addslashes(str_replace(["\r", "\n"], '', $store_dropdown_html)); 

        $js_template = "<div class=\'item-box\'><div class=\'item-box-header\'><button type=\'button\' class=\'btn-icon remove-item-btn\' title=\'" . __('Remover Item') . "\'><i class=\'fas fa-times\'></i></button></div><div class=\'item-box-body\'><div class=\'form-field\'><label>" . __('Nº do Chamado', 'equipmentexit') . "</label><input type=\'number\' name=\'tickets_id[]\' value=\'\' class=\'glpi_input\'></div><div class=\'form-field\'><label>" . __('Nº do Patrimônio', 'equipmentexit') . "</label><input type=\'text\' name=\'patrimonio[]\' value=\'\' class=\'glpi_input\'></div><div class=\'form-field\'><label>" .
__('Nome do Equipamento', 'equipmentexit') . " <span class=\'required\'>*</span></label><input type=\'text\' name=\'equipamento_nome[]\' value=\'\' class=\'glpi_input\'></div><div class=\'form-field\'><label>" . __('Tipo do Equipamento', 'equipmentexit') . " <span class=\'required\'>*</span></label><input type=\'text\' name=\'equipamento_tipo[]\' value=\'\' class=\'glpi_input\'></div><div class=\'form-field\'><label>" . __('Quantidade', 'equipmentexit') . " <span class=\'required\'>*</span></label><input type=\'number\' name=\'quantidade[]\' value=\'1\' class=\'glpi_input\' style=\'max-width: 120px;\'></div><div class=\'form-field\'><label>" . __('Loja de Destino', 'equipmentexit') . " <span class=\'required\'>*</span></label>$store_dropdown_html</div></div></div>";
        
        echo "<script type='text/javascript'>
            $(document).ready(function() {
                $('#add_item_button').on('click', function() {
                    var item_template = '$js_template';
                    $('#equipment_items_container').append(item_template);
                });
                $('#equipment_items_container').on('click', '.remove-item-btn', function() {
                    if ($('.item-box').length > 1) {
                         $(this).closest('.item-box').remove();
                    } else {
                        alert('" . __('Você deve manter pelo menos um item.', 'equipmentexit') . "');
                    }
                });
            });
        </script>";
    }
    
    private function displayRadioButtons($name, $options, $selectedValue = '') {
        foreach ($options as $value => $label) {
             $checked = ($value == $selectedValue) ? 'checked' : '';
            echo "<label style='margin-right: 15px; font-weight: normal;'>";
            echo "<input type='radio' name='$name' value='$value' $checked> $label";
            echo "</label>";
        }
    }
    
    function prepareInputForAdd($input) {
        if (!isset($input['users_id_requester']) || empty($input['users_id_requester'])) {
            $input['users_id_requester'] = Session::getLoginUserID();
        }
         if (!isset($input['date_request']) || empty($input['date_request'])) {
            $input['date_request'] = $_SESSION["glpi_currenttime"]; 
        }
        $input['status'] = 1; 
        if (empty($input['local_origem']) || empty($input['tipo_movimentacao']) || 
            empty($input['reason']) || empty($input['date_exit_planned'])) {
             Session::addMessageAfterRedirect(__('Por favor, preencha todos os campos obrigatórios (*).', 'equipmentexit'), true, ERROR);
             Html::back(); 
             return false; 
        }
        if (empty($input['equipamento_nome']) || !is_array($input['equipamento_nome']) || empty($input['equipamento_nome'][0])) {
             Session::addMessageAfterRedirect(__('Você deve adicionar pelo menos um equipamento.', 'equipmentexit'), true, ERROR);
             Html::back(); 
             return false;
        }
        foreach ($input['equipamento_nome'] as $key => $nome) {
            if (empty($nome) || 
                empty($input['equipamento_tipo'][$key]) || 
                empty($input['quantidade'][$key]) || 
                empty($input['loja_destino'][$key])) {
                 Session::addMessageAfterRedirect(__('Todos os itens devem ter Nome, Tipo, Quantidade e Loja de Destino preenchidos.', 'equipmentexit'), true, ERROR);
                 Html::back(); 
                 return false;
            }
        }
        if (!isset($input['name']) || empty($input['name'])) {
            $input['name'] = sprintf("Solicitação de %s para %s", $input['local_origem'], $input['equipamento_nome'][0]);
        }
        return $input;
    }
    
     function post_addItem() {
         parent::post_addItem();
         $this->saveItems($_POST); 
     }
     
     function post_updateItem($history = true) {
         global $DB;
         parent::post_updateItem($history);
         $DB->delete(self::$item_table, [self::$fk_field => $this->getID()]); 
         $this->saveItems($_POST); 
     }
     
     private function saveItems($input) {
        global $DB;
        $request_id = $this->getID();
        if ($request_id > 0 && isset($input['equipamento_nome']) && is_array($input['equipamento_nome'])) {
            foreach ($input['equipamento_nome'] as $key => $nome) {
                $item_data = [ 
                    self::$fk_field        => $request_id,
                    'equipamento_nome'     => $nome,
                    'equipamento_tipo'     => $input['equipamento_tipo'][$key] ?? '', 
                    'tickets_id'           => (int)($input['tickets_id'][$key] ?? 0),
                    'quantidade'           => (int)($input['quantidade'][$key] ?? 1),
                    'patrimonio'           => $input['patrimonio'][$key] ?? '',
                    'loja_destino'         => $input['loja_destino'][$key] ?? ''
                ];
                $DB->insert(self::$item_table, $item_data);
            }
        }
     }
     
    function pre_purgeItem() {
        global $DB;
        $DB->delete(self::$item_table, [self::$fk_field => $this->getID()]); 
        parent::pre_purgeItem();
    }
    
    function showFormButtons($options = []) { 
        $ID = $this->getID();
        $can_action = self::canCreate();
        if (!$can_action && Session::haveRight('config', UPDATE)) {
            $can_action = true;
        }
        if (!$can_action) {
            return;
        }
        echo "<tr class='tab_bg_1'>";
        echo "<td class='center' colspan='4'>";
        if ($ID > 0) {
            echo Html::submit(__('Salvar'), ['name' => 'update']); 
            if (empty($this->fields["is_deleted"])) {
                echo Html::submit(__('Mover para a lixeira'), ['name' => 'delete']); 
            } else {
                echo Html::submit(__('Restaurar'), ['name' => 'restore']); 
                echo Html::submit(__('Excluir permanentemente'), ['name' => 'purge']); 
            }
        } else {
            echo Html::submit(__('Adicionar'), ['name' => 'add']); 
            echo "<span style='margin-left: 10px;'>";
            echo "<input type='checkbox' name='_add_another' value='1' id='add_another_checkbox'>";
            echo " <label for='add_another_checkbox'>" . __('Adicionar e continuar') . "</label>";
            echo "</span>";
        }
        echo "</td>";
        echo "</tr>";
    }
}
?>