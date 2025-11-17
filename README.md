# Plugin Saída de Equipamentos (Equipment Exit) para GLPI

Plugin desenvolvido para gerenciar o fluxo de autorização e logística de saída de equipamentos e materiais da empresa. Permite o controle de movimentações entre corporativo, lojas e centros de distribuição, com aprovações em múltiplas etapas e geração de termo de responsabilidade em PDF.

## 🚀 Compatibilidade

- *GLPI:* 10.0.x e 11.x
- *PHP:* 8.1 ou superior

## 📋 Funcionalidades

- *Solicitação Simplificada:* Formulário intuitivo para solicitar a saída de múltiplos itens.
- *Fluxo de Aprovação em 4 Etapas:*
  1.  *Solicitante:* Cria o pedido.
  2.  *Gerente:* Primeira aprovação administrativa.
  3.  *Governança:* Validação de conformidade/inventário.
  4.  *Segurança (Saída):* Conferência física na portaria de origem.
  5.  *Segurança (Chegada):* Conferência física no destino (encerra o fluxo).
- **Restrição por Loja:** Usuários de segurança visualizam apenas solicitações pertinentes à sua loja (Origem ou Destino).
- **Termo em PDF:** Geração automática do "Termo de Movimentação" com histórico de assinaturas digitais (quem aprovou e quando).
- **Logo Personalizável:** Upload de logo para o PDF via painel de configuração.
- **Interface Responsiva:** Cards visuais para facilitar a gestão das filas.

## 🛠️ Instalação

1. **Download:**
   Copie a pasta `equipmentexit` para o diretório de plugins do seu GLPI:
   `GLPI_ROOT/plugins/equipmentexit`

2. **Permissões (Importante para Linux/Docker):**
   Certifique-se de que o servidor web tenha permissão de escrita nas pastas de CSS e Imagens.
   ```bash
   # Exemplo para Docker/Linux (ajuste o usuário www-data conforme seu ambiente)
   chown -R www-data:www-data /var/www/html/plugins/equipmentexit
   chmod -R 755 /var/www/html/plugins/equipmentexit

3.  **Ativação:**
      - Acesse o GLPI como Super-Admin.
      - Vá em **Configurar \> Plugins**.
      - Clique em **Instalar** no plugin "Saída de Equipamentos".
      - Clique em **Habilitar**.

## ⚙️ Configuração

Após instalar, acesse o menu **Configurar \> Saída de Equipamentos** (ou através da aba Geral).

Nesta tela, você deve definir quem são os aprovadores:

1.  **Logo do PDF:** Faça upload de uma imagem PNG para o cabeçalho do termo.
2.  **Gerentes:** Adicione usuários que podem aprovar a 1ª etapa.
3.  **Governança:** Adicione usuários que aprovam a 2ª etapa.
4.  **Segurança Patrimonial:** Adicione usuários de portaria.
    *Atenção:* Ao adicionar um segurança, você *deve* selecionar a Loja. Ele só poderá liberar saídas/entradas vinculadas a esta loja.
5.  **Solicitantes Autorizados:** Usuários que têm permissão para abrir novas requisições (além dos administradores).

## 🖥️ Como Usar

### 1\. Criar Solicitação

O usuário acessa **Ferramentas \> Saída de Equipamentos** (ou pelo menu superior).

  - Clica em "Nova Solicitação".
  - Preenche Origem, Tipo de Movimentação, Justificativa e adiciona os Itens (Patrimônio, Chamado, etc).

### 2\. Aprovação (Gerente e Governança)

Os aprovadores acessam o menu **Plugins \> Saídas - Aprovações**.

  - Eles verão cards com status **Laranja** (Pendente).
  - Podem "Aprovar Etapa" ou "Rejeitar" (com justificativa obrigatória).

### 3\. Portaria (Segurança)

O segurança acessa a mesma tela de aprovações.

  - **Na Saída:** Ele visualiza o card quando o status é 3 (Pendente Saída) E a origem é a loja dele. Ele confere os itens e clica em "Confirmar Saída".
  - **Na Chegada:** Ele visualiza o card quando o status é 4 (Em Trânsito) E o destino é a loja dele. Ele clica em "Confirmar Chegada".

### 4\. Impressão

A qualquer momento após a aprovação da Governança, é possível clicar no botão **Imprimir** no card da solicitação para gerar o PDF do termo.

## 🐛 Solução de Problemas Comuns

**O CSS não carrega (Tela sem estilo):**
Se você usa Docker, o GLPI pode ter dificuldades em achar o caminho do CSS.

1.  Verifique se a pasta `plugins/equipmentexit/css` existe e contém o arquivo `equipmentexit.css`.
2.  Rode o comando de permissão novamente: `chown -R www-data:www-data plugins/equipmentexit`.
3.  O plugin já possui um mecanismo de injeção direta de CSS para contornar erros 404 em ambientes virtualizados.

## 📁 Estrutura de Pastas


equipmentexit/
├── css/                 # Estilos (injetados via PHP)
├── front/               # Telas (Formulários, Listagens, Config, PDF)
├── images/              # Logo customizável
├── inc/                 # Bibliotecas externas (FPDF)
├── src/                 # Classes PHP (Autoload PSR-4: Request, Menus)
├── hook.php             # Instalação/Desinstalação (DB)
├── plugin.xml           # Manifesto de versão
└── setup.php            # Inicialização e Hooks

**Desenvolvido por:** Kawan Costa
**Licença:** GPLv3
