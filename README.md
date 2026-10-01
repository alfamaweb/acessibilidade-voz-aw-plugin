# Acessibilidade Voz AW

Plugin WordPress que lê o conteúdo principal da página em voz alta, usando a
síntese de voz nativa do navegador. Enquanto lê, destaca a frase atual com
fundo suave e a palavra atual com fundo forte, rolando a página para
acompanhar.

Sem cadastro, chave, API paga, telemetria ou chamadas HTTP. O JavaScript do
front-end não usa bibliotecas; o Color Picker e o jQuery do WordPress são
carregados apenas no painel.

Uso interno da Alfamaweb: a interface é em português e não há arquivos de
tradução. Complementa a acessibilidade do site — não substitui um leitor
de tela.

| | |
|---|---|
| Versão | 1.3.0 |
| WordPress | 6.0 ou superior |
| PHP | 7.4 ou superior |
| Licença | GPL-2.0-or-later |

## Instalação

1. Clique com o botão direito na pasta `acessibilidade-voz-aw` e escolha
   **Enviar para → Pasta compactada**.
2. **Plugins → Adicionar plugin → Enviar plugin → Instalar → Ativar**.
3. Clique em **Configurações** ao lado do plugin na listagem.
4. Limpe o cache do site se as alterações não aparecerem.

O tema precisa executar `wp_head()` e `wp_footer()`.

> **Não use o `Compress-Archive` do PowerShell.** Ele grava os caminhos
> internos com barra invertida, o que viola a especificação ZIP, e o
> WordPress instala o plugin na estrutura errada — o sintoma é "O arquivo do
> plugin não existe" na ativação. O botão direito do Explorer e o `tar`
> embutido no Windows geram o pacote correto:
>
> ```
> tar -a -c -f acessibilidade-voz-aw.zip acessibilidade-voz-aw
> ```

## Configurações

Link **Configurações** na tela Plugins, ou **Configurações → Acessibilidade
Voz AW**.

| Campo | Valores | Padrão |
|---|---|---|
| Idioma da leitura | automático (segue o site) ou fixo | automático |
| Posição | inferior/meio × direita/esquerda | inferior direita |
| Distância lateral | 0 a 200 px | 16 |
| Distância vertical | 0 a 400 px | 88 |
| Tamanho do botão | 48, 56 ou 64 px | 56 |
| Cor do botão | hexadecimal | `#334155` |
| Cor do destaque | hexadecimal | `#ffe066` |

As cores de texto sobre o botão e sobre o destaque são calculadas pela
luminância relativa da WCAG. Qualquer cor escolhida mantém contraste acima
de 4.5:1 — não dá para configurar algo ilegível.

Em idioma automático o plugin segue **Configurações → Geral**. Um site
brasileiro configurado como inglês faz o navegador ler português com voz
inglesa; o campo fixo resolve, mas corrigir o idioma do site é melhor,
porque ele também afeta datas e o painel.

Aumente a distância vertical para o botão não colidir com outros widgets
flutuantes, como WhatsApp ou barras de acessibilidade.

## Como funciona

O difícil não é falar — a API de síntese do navegador faz isso em três
linhas. O difícil é saber **o que** ler, e **onde** esse texto está no DOM
para conseguir destacá-lo enquanto é falado. O plugin resolve isso em quatro
etapas.

### 1. Captura com mapa

`aw-voice-reader-content.js` procura `main`, depois `.entry-content`, depois
`article`, e percorre a árvore pulando navegação, cabeçalho, rodapé,
formulários, widgets, modais e conteúdo oculto. Nunca usa `document.body`,
para não ler a interface inteira.

Durante a travessia monta duas coisas ao mesmo tempo: a string que será
falada e um mapa de segmentos `{inicio, fim, node, nodeOffset}`.

A normalização de espaços acontece aí, e **não depois**: colapsar espaços no
final deslocaria todos os índices já registrados, e o destaque cairia na
palavra errada com erro crescente ao longo da página.

Corridas de texto viram segmentos exatos, com correspondência de um para um
entre a string e o nó. Corridas de espaço viram um único caractere e um
segmento inexato — palavra nenhuma cai nesses, então a imprecisão não afeta
o destaque.

Para excluir um componente próprio, marque-o com `data-aw-voice-reader-ignore`.

### 2. Fila de fala

`chunks()` divide em blocos de até 180 unidades UTF-16, cortando em
pontuação e nunca no meio de um par UTF-16. Cada bloco carrega seu
deslocamento no texto completo.

### 3. Sincronia

O evento `onboundary` informa o `charIndex` relativo ao bloco que está sendo
falado. Somado ao deslocamento do bloco vira índice global; uma busca
binária no mapa encontra o segmento; o nó e o offset montam um `Range` do
DOM.

### 4. Pintura

Esse `Range` vai para `CSS.highlights`. A API pinta por cima do texto **sem
alterar o DOM** — nenhum elemento do tema é envolvido em `span`, então
layout, eventos e page builders não são afetados.

O destaque do bloco inteiro é o piso, aplicado ao começar cada bloco; onde
o `onboundary` existe, a primeira palavra refina por cima.

### Contraste calculado no servidor

`Color::foreground()` implementa a fórmula de luminância relativa da WCAG em
PHP e devolve preto ou branco para a cor escolhida no painel. O resultado
viaja como variável CSS. Por isso nenhuma combinação configurável fica
ilegível, e o cálculo não custa nada no navegador.

### Escolha de voz

Pontuação sobre `speechSynthesis.getVoices()`, calculada uma vez por
leitura. A região exata do idioma pesa mais que a tecnologia — uma voz pt-BR
robótica pronuncia português brasileiro melhor que uma pt-PT natural. Acima
dos marcadores de produto vem `voice.default`, a voz que o próprio sistema
do usuário declara.

Voz local **não** é prioridade: no Windows as únicas pt-BR instaladas
localmente são Microsoft Daniel e Maria, ambas SAPI antigas e de som
robótico, enquanto as "(Natural)" em rede são neurais. Está em
`escolherVoz()`, no `aw-voice-reader.js`.

## Estrutura

```
acessibilidade-voz-aw.php        carrega as classes e registra os hooks
uninstall.php                    remove a option, inclusive em multisite
includes/class-plugin.php        quando o leitor entra na página
includes/class-settings.php      option, validação e tela do painel
includes/class-assets.php        enfileira CSS e JS
includes/class-renderer.php      markup do rodapé
includes/class-color.php         contraste e conversão para rgba
assets/js/*-content.js           captura o texto e o mapa texto/DOM
assets/js/*-highlight.js         converte índices em Range, pinta e rola
assets/js/aw-voice-reader.js     fila de fala e controles
```

Namespace `Alfamaweb\VoiceReader`, sem Composer e sem dependências.

Os prefixos `aw-voice-reader` nos arquivos de asset, nos IDs de CSS e na
chave da option são anteriores ao rename do plugin e foram mantidos de
propósito: trocar a chave da option descartaria as configurações já salvas,
e trocar os prefixos de CSS seria churn sem ganho.

## Limitações conhecidas

Nenhuma destas impede ouvir a página; todas degradam sozinhas.

- **Sem `onboundary`**, o destaque marca o bloco inteiro em vez da palavra.
  Comum no Android.
- **Sem `CSS.highlights`** (navegadores antigos), a leitura funciona sem
  destaque.
- **A voz escolhida costuma depender de rede**, porque as vozes locais em
  português são antigas e robóticas. Ela demora um instante a mais para
  começar e para se a conexão cair. Foi uma troca deliberada de
  disponibilidade por qualidade.
- **Pausar e continuar são instáveis no Chrome para Android.**
- **Não lê** texto em imagens, canvas, iframes ou Shadow DOM.
- **Pop-ups sem semântica reconhecível** podem ser lidos; use o atributo de
  exclusão no componente.
- **Se a página mudar durante a leitura**, o destaque é limpo e a fala segue.
- **A síntese de voz é global ao documento.** O plugin não inicia se outra
  leitura estiver ativa e só cancela a própria fala, mas outro componente que
  fale ao mesmo tempo pode ser afetado por pause, resume ou cancel.
- **Não há detecção de sobreposição** com widgets de terceiros. Valide a
  posição no site.

## Antes de publicar uma versão

Roteiro manual, o essencial primeiro:

- [ ] Ouvir uma página longa; pausar, continuar, parar e reiniciar.
- [ ] Confirmar o destaque da palavra e da frase, e a rolagem acompanhando.
- [ ] Confirmar que menu, rodapé, widgets e modais não são lidos.
- [ ] Testar uma página sem `main`, `.entry-content` nem `article`.
- [ ] Abrir o painel por teclado e fechar com Escape; conferir o anel de foco.
- [ ] Salvar as sete configurações e conferir o efeito no site.
- [ ] Conferir a posição com os outros botões flutuantes do site ativos.
- [ ] Testar em Android: destaque em modo bloco, e pausar/continuar.
- [ ] Testar nos navegadores que o público do site realmente usa.

## Changelog

### 1.3.0

Renomeado para Acessibilidade Voz AW. A chave da option e os prefixos de
CSS/JS foram mantidos, então configurações salvas sobrevivem à atualização.

Corrige o destaque do primeiro bloco: a detecção de ausência do `onboundary`
chegava um bloco atrasada, e no Android o primeiro bloco de cada leitura saía
sem destaque nenhum. O destaque de bloco passou a ser o piso em vez de um
plano B, e a máquina de estados que inferia o suporte desapareceu.

Corrige vazamento: ao terminar a leitura naturalmente o mapa não era
liberado, retendo uma referência de nó de DOM por palavra da página pelo
resto da vida da aba.

O anel de foco deixa de ser preto. Passa a ser desenhado por dentro do botão
na cor do ícone, que já tem contraste garantido contra o fundo do botão — o
anel anterior dependia do fundo da página para ser visível.

Desempenho no caminho quente: a voz passa a ser escolhida uma vez por leitura
em vez de uma vez por bloco; a frase só é recalculada quando a leitura sai
dos seus limites; os objetos `Highlight` são reaproveitados em vez de
recriados a cada palavra; e a medição de layout da rolagem só acontece na
troca de frase.

Simplificações sem mudança de comportamento, verificadas por comparação com
a versão anterior: `chunks()` deixa de ter três cópias do mesmo ajuste de
offset, a captura resolve o estilo computado uma vez por nó em vez de duas,
as posições e os limites numéricos passam a ter uma única fonte lida pela
validação e pelo painel, e `uninstall.php` usa a constante da classe em vez
de repetir o nome da option.

### 1.2.2

Corrige a qualidade da voz. O plugin priorizava vozes locais, o que no
Windows significava escolher Microsoft Daniel ou Maria — as duas únicas
pt-BR instaladas, ambas antigas e robóticas — ignorando as dezoito vozes
neurais "(Natural)" disponíveis em rede.

### 1.2.1

Link **Configurações** na tela de plugins: sem ele a tela existia mas ficava
escondida num submenu. Campo **Idioma da leitura**, para sites configurados
em idioma diferente do conteúdo.

### 1.2.0

Destaque da frase e da palavra durante a leitura, com rolagem que respeita
`prefers-reduced-motion`. Botão circular com ícone. Painel de duas para seis
configurações, com migração automática das instalações 1.x. JavaScript
dividido em três módulos.

### 1.1.1

Corrige a leitura em sites com OneTap: a classe que o plugin escreve no
`body` casava com a exclusão do widget e descartava a página inteira. Classes
de `body` e `html` deixam de ser consideradas na exclusão de componentes.

### 1.1.0

Reorganização em classes com namespace. Idioma passa a seguir o WordPress.
Corrige o nome acessível do botão (WCAG 2.5.3). Confirmação de gravação no
painel. Restringe a exclusão de `.widget` e `.menu`, que descartava conteúdo
legítimo. Corrige laço infinito na divisão de texto com par UTF-16 quebrado.
Adiciona `uninstall.php`.

### 1.0.0

Versão inicial.
