<?php


// 1. Includes
include ('../../../inc/includes.php');
require_once(__DIR__ . '/../inc/Request.php'); 
require_once(__DIR__ . '/../inc/pdf/fpdf.php'); 

// 3. Namespaces necessários
use GlpiPlugin\Equipmentexit\Request as PluginEquipmentexitRequest;
use User; 

// Função auxiliar de conversão
function fpdf_encode($string) {
    if (empty($string)) {
        return '';
    }
    $encoded = @iconv("UTF-8", "ISO-8859-1//TRANSLIT", $string);
    if ($encoded === false) {
        return $string; 
    }
    return $encoded;
}

// 4. Classe de PDF Personalizada
class PDF extends FPDF {
    function Header() {
        // *** INÍCIO DA ATUALIZAÇÃO (Logo Customizável) ***
        $custom_logo = __DIR__ . '/../images/custom_logo.png';
        $default_logo = __DIR__ . '/../images/mercadocar_logo.png';
        
        $logo_to_use = $default_logo; // Padrão
        if (file_exists($custom_logo)) {
            $logo_to_use = $custom_logo; // Usa o customizado se existir
        }
        
        // Imagem (X, Y, Largura)
        $this->Image($logo_to_use, 10, 10, 40); 
        // *** FIM DA ATUALIZAÇÃO ***
        
        // *** INÍCIO DA CORREÇÃO (v5.2) ***
         // Mover o cursor 15mm para baixo ANTES de escrever o título
        // Isso impede a sobreposição com a logo
        $this->Ln(15); 
        // *** FIM DA CORREÇÃO ***
        
        // Título Principal
        $this->SetFont('Arial','B',16);
        $this->SetTextColor(0, 0, 0); 
        $this->Cell(0, 10, fpdf_encode('TERMO DE MOVIMENTAÇÃO DE EQUIPAMENTOS DE T.I'), 0, 1, 'C');
        $this->Ln(4);
        
        // Linha Divisória
        $this->SetDrawColor(0, 0, 0); 
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15); 
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(128); 
        $this->Cell(0, 10, fpdf_encode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function SectionTitle($title) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(240, 240, 240); 
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, ' ' . fpdf_encode($title), 'B', 1, 'L', true); 
        $this->Ln(3);
    }
    
     function Field($key, $value) {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(35, 6, fpdf_encode($key . ':'), 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->MultiCell(0, 6, fpdf_encode($value), 0, 'L');
    }
    
    function CommentBlock($title, $comment) {
        if (!empty($comment)) {
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(0, 6, fpdf_encode($title . ':'), 0, 1, 'L');
            $this->SetFont('Arial', '', 9);
            $this->MultiCell(0, 6, fpdf_encode($comment), 1, 'L'); 
            $this->Ln(2);
        }
     }
}

// --- 5. Busca de Dados ---
global $DB;
$plugin_name = 'equipmentexit';

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($request_id <= 0) {
    Html::displayErrorAndDie('ID da solicitação inválido.');
}

// Buscar dados do cabeçalho
$table = PluginEquipmentexitRequest::getTable();
$sql_check = "SELECT * FROM `$table` WHERE `id` = " . $request_id . " AND `is_deleted` = 0";
$request_query = $DB->query($sql_check);
if (!$request_query || $DB->numrows($request_query) == 0) {
    Html::displayErrorAndDie('Solicitação não encontrada.');
}
$request_data = $DB->fetchAssoc($request_query);

// VERIFICAÇÃO DE STATUS
if ($request_data['status'] < 3 || $request_data['status'] == 9) {
     Html::displayErrorAndDie(__('Esta solicitação ainda não foi aprovada ou foi rejeitada.', $plugin_name));
}

// Buscar nome do solicitante
$requester_user = new User();
$requester_name = __('Desconhecido');
if ($requester_user->getFromDB($request_data['users_id_requester'])) {
    $requester_name = $requester_user->fields['firstname'] . " " . $requester_user->fields['realname'];
}

// Buscar os múltiplos itens
$item_table = PluginEquipmentexitRequest::$item_table;
$fk_field = PluginEquipmentexitRequest::$fk_field;
$sql_items = "SELECT * FROM `$item_table` WHERE `$fk_field` = " . $request_id;
$items_query = $DB->query($sql_items);
$request_items = iterator_to_array($items_query);

// Buscar dados dos aprovadores
function getSignatureDetails($user_id, $date_field, $comment_field = '') {
    $name = __('Aguardando Ação');
    $date_str = '';
    if (!empty($user_id)) {
        $user = new User();
        if ($user->getFromDB($user_id)) {
            $name = $user->fields['firstname'] . " " . $user->fields['realname'];
        }
        if (!empty($date_field)) {
            $date_str = Html::convDateTime($date_field);
        }
    }
    return ['name' => $name, 'date' => $date_str, 'comment' => $comment_field];
}
$gerente_details     = getSignatureDetails($request_data['users_id_gerente'], $request_data['date_gerente'], $request_data['comment_gerente']);
$governanca_details  = getSignatureDetails($request_data['users_id_governanca'], $request_data['date_governanca'], $request_data['comment_governanca']);
$seg_saida_details   = getSignatureDetails($request_data['users_id_seg_saida'], $request_data['date_seg_saida'], $request_data['comment_seg_saida']);
$seg_chegada_details = getSignatureDetails($request_data['users_id_seg_chegada'], $request_data['date_seg_chegada'], $request_data['comment_seg_chegada']);

// --- 6. GERAÇÃO DO PDF ---
$pdf = new PDF(); 
$pdf->AliasNbPages(); 
$pdf->AddPage(); 
$pdf->SetFont('Arial','',10);
$pdf->SetMargins(10, 10, 10); 

// --- Bloco de Informações Gerais ---
$pdf->SectionTitle('DETALHES DA SOLICITAÇÃO');
$pdf->Field('ID da Solicitação', $request_data['id']);
$pdf->Field('Data da Solicitação', Html::convDateTime($request_data['date_request']));
$pdf->Field('Solicitante', $requester_name);
$pdf->Field('Local de Origem', $request_data['local_origem']);
$pdf->Field('Data Prevista Saída', Html::convDateTime($request_data['date_exit_planned']));
if (!empty($request_data['date_return_planned'])) {
    $pdf->Field('Data Prevista Retorno', Html::convDateTime($request_data['date_return_planned']));
}
$tipos = [
    'corporativo' => 'Saída para Departamento Corporativo',
    'loja'        => 'Saída para Loja',
    'cd'          => 'Saída para Centro de Distribuição (CD)',
];
$tipo_selecionado = $tipos[$request_data['tipo_movimentacao']] ?? 'N/A';
$pdf->Field('Tipo de Movimentação', $tipo_selecionado);
$pdf->Ln(5);
$pdf->CommentBlock('Justificativa', $request_data['reason']);
$pdf->Ln(5);


// --- Bloco de Equipamentos ---
$pdf->SectionTitle('EQUIPAMENTOS A SEREM MOVIMENTADOS');
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(230,230,230); 
$pdf->Cell(20, 7, fpdf_encode('Chamado'), 1, 0, 'C', true);
$pdf->Cell(50, 7, fpdf_encode('Equipamento'), 1, 0, 'C', true);
$pdf->Cell(40, 7, fpdf_encode('Tipo'), 1, 0, 'C', true);
$pdf->Cell(15, 7, fpdf_encode('Qtd.'), 1, 0, 'C', true);
$pdf->Cell(30, 7, fpdf_encode('Patrimônio'), 1, 0, 'C', true);
$pdf->Cell(35, 7, fpdf_encode('Loja Destino'), 1, 1, 'C', true); 

$pdf->SetFont('Arial','',8);
$pdf->SetFillColor(255,255,255); 
if (count($request_items) > 0) {
    foreach($request_items as $item) {
        $pdf->Cell(20, 6, $item['tickets_id'] > 0 ? $item['tickets_id'] : '', 'LR', 0, 'C');
        $pdf->Cell(50, 6, fpdf_encode($item['equipamento_nome']), 'LR', 0, 'L');
        $pdf->Cell(40, 6, fpdf_encode($item['equipamento_tipo']), 'LR', 0, 'L');
        $pdf->Cell(15, 6, $item['quantidade'], 'LR', 0, 'C');
        $pdf->Cell(30, 6, fpdf_encode($item['patrimonio']), 'LR', 0, 'L');
        $pdf->Cell(35, 6, fpdf_encode($item['loja_destino']), 'LR', 1, 'L');
    }
} else {
    $pdf->Cell(190, 6, fpdf_encode('Nenhum item encontrado para esta solicitação.'), 'LRB', 1, 'C');
}
$pdf->Cell(190, 0, '', 'T', 1); 
$pdf->Ln(10);

// --- Bloco de Aprovações e Assinaturas ---
$pdf->SectionTitle('REGISTRO DE APROVAÇÕES E MOVIMENTAÇÕES');
$pdf->Ln(2);

$box_height = 30; // Altura fixa da caixa

// Função para desenhar uma caixa de assinatura
function drawDetailedSignatureBox($pdf, $title, $name, $date, $comment) {
    global $box_height; 
    $start_x = $pdf->GetX();
    $start_y = $pdf->GetY();
    $box_width = 95; 
    $padding = 2;
    
    $pdf->Rect($start_x, $start_y, $box_width, $box_height);
    
    // Título
    $pdf->SetXY($start_x + $padding, $start_y + $padding);
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell($box_width - ($padding * 2), 5, fpdf_encode($title), 0, 1, 'L');
    
    // Nome
    $pdf->SetX($start_x + $padding);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell($box_width - ($padding * 2), 5, fpdf_encode($name), 0, 1, 'L');
    
    // Data
    $pdf->SetX($start_x + $padding);
    $pdf->Cell($box_width - ($padding * 2), 5, 'Data: ' . $date, 0, 1, 'L');
    
    // Comentário (com MultiCell dentro da área restante)
    if (!empty($comment)) {
        $pdf->SetX($start_x + $padding);
        $pdf->SetFont('Arial','I',8); 
        $pdf->SetTextColor(80, 80, 80); 
        $pdf->MultiCell($box_width - ($padding * 2), 4, fpdf_encode('Obs: ' . $comment), 0, 'L');
        $pdf->SetTextColor(0, 0, 0); 
    }
    
    // Reposiciona o cursor
    $pdf->SetXY($start_x + $box_width, $start_y); 
}

// --- Renderiza as caixas ---
// Linha 1 de Assinaturas
drawDetailedSignatureBox($pdf, 'Solicitante', $requester_name, '(Assinatura manual)', '');
$pdf->Cell(5); 
drawDetailedSignatureBox($pdf, 'Gerente', $gerente_details['name'], $gerente_details['date'], $gerente_details['comment']);
$pdf->Ln($box_height + 5); 

// Linha 2 de Assinaturas
$pdf->SetX(10); 
drawDetailedSignatureBox($pdf, 'Governança', $governanca_details['name'], $governanca_details['date'], $governanca_details['comment']);
$pdf->Cell(5);
drawDetailedSignatureBox($pdf, 'Segurança de Saída', $seg_saida_details['name'], $seg_saida_details['date'], $seg_saida_details['comment']);
$pdf->Ln($box_height + 5); 

// Linha 3 de Assinaturas
$pdf->SetX(10); 
drawDetailedSignatureBox($pdf, 'Segurança de Chegada', $seg_chegada_details['name'], $seg_chegada_details['date'], $seg_chegada_details['comment']);
$pdf->Ln(); 

// --- 7. Saída ---
$pdf->Output('I', 'termo_saida_id_' . $request_id . '.pdf');
exit; 
?>