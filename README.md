# Acessibilidade Voz AW

Plugin WordPress que lê o conteúdo principal da página em voz alta com a
síntese nativa do navegador, destacando a frase e a palavra conforme fala.

Sem cadastro, chave, API paga, telemetria ou chamadas HTTP. O JavaScript do
front-end não usa bibliotecas. Uso interno da Alfamaweb: interface em
português, sem arquivos de tradução. Complementa a acessibilidade do site —
não substitui um leitor de tela.

| | |
|---|---|
| Versão | 1.3.0 |
| Requer | WordPress 6.0+, PHP 7.4+ |
| Licença | GPL-2.0-or-later |

## Instalação

Compacte a pasta `acessibilidade-voz-aw` (botão direito → Enviar para →
Pasta compactada), depois **Plugins → Adicionar plugin → Enviar plugin →
Instalar → Ativar**. O link **Configurações** aparece ao lado do plugin na
listagem.

O tema precisa executar `wp_head()` e `wp_footer()`.

> **Não use o `Compress-Archive` do PowerShell.** Ele grava os caminhos com
> barra invertida, violando a especificação ZIP, e o WordPress instala na
> estrutura errada — o sintoma é "O arquivo do plugin não existe" na
> ativação. O Explorer e o `tar` do Windows geram o pacote correto:
> `tar -a -c -f acessibilidade-voz-aw.zip acessibilidade-voz-aw`

## Configurações

**Configurações → Acessibilidade Voz AW**

| Campo | Valores | Padrão |
|---|---|---|
| Idioma da leitura | automático ou fixo | automático |
| Posição | inferior/meio × direita/esquerda | inferior direita |
| Distância lateral | 0 a 200 px | 16 |
| Distância vertical | 0 a 400 px | 88 |
| Tamanho do botão | 48, 56 ou 64 px | 56 |
| Cor do botão | hexadecimal | `#334155` |
| Cor do destaque | hexadecimal | `#ffe066` |

As cores de texto sobre o botão e sobre o destaque saem da luminância
relativa da WCAG, então qualquer cor escolhida mantém contraste acima de
4.5:1 — não dá para configurar algo ilegível.

Em idioma automático o plugin segue **Configurações → Geral**. Um site
brasileiro configurado como inglês faz o navegador ler português com voz
inglesa; o campo fixo resolve, mas corrigir o idioma do site é melhor.

Aumente a distância vertical para o botão não colidir com outros widgets
flutuantes, como WhatsApp ou barras de acessibilidade.

## Como funciona

O difícil não é falar — a API do navegador faz isso em três linhas. O difícil
é saber **o que** ler e **onde** esse texto está no DOM para destacá-lo
enquanto é falado.

**Captura.** `aw-voice-reader-content.js` procura `main`, `.entry-content`,
`article`, e percorre a árvore pulando navegação, rodapé, formulários,
widgets, modais e conteúdo oculto. Monta ao mesmo tempo a string que será
falada e um mapa de segmentos `{inicio, fim, node, nodeOffset}`. A
normalização de espaços acontece durante a travessia e não depois: colapsar
no final deslocaria todos os índices já registrados.

**Fala.** `chunks()` divide em blocos de até 180 unidades UTF-16, cortando em
pontuação e nunca no meio de um par UTF-16. Cada bloco carrega seu
deslocamento no texto completo.

**Sincronia.** O evento `onboundary` dá o `charIndex` do bloco. Somado ao
deslocamento vira índice global; busca binária no mapa acha o segmento; nó e
offset montam um `Range`.

**Pintura.** O `Range` vai para `CSS.highlights`, que pinta sem alterar o DOM
— nenhum elemento é envolvido em `span`, então layout, eventos e page
builders não são afetados. O bloco inteiro é o piso, aplicado ao começar cada
bloco; onde o `onboundary` existe, a palavra refina por cima.

**Voz.** Pontuação sobre `speechSynthesis.getVoices()`, calculada uma vez por
leitura. Região exata do idioma pesa mais que tecnologia; entre vozes da mesma
região vencem as neurais; uma lista de nomes desempata. Voz local não é
prioridade: no Windows as pt-BR locais são SAPI antigas e soam pior que as
neurais em rede.

Para excluir um componente próprio da leitura, marque-o com
`data-aw-voice-reader-ignore`.

## Estrutura

```
acessibilidade-voz-aw.php     constantes, requires e boot
uninstall.php                 remove a option, inclusive em multisite
includes/class-plugin.php     quando o leitor entra na página
includes/class-settings.php   option, validação e tela do painel
includes/class-assets.php     enfileira CSS e JS
includes/class-renderer.php   markup do rodapé
includes/class-color.php      contraste e conversão para rgba
assets/js/*-content.js        captura: texto + mapa texto/DOM
assets/js/*-highlight.js      Range, pintura e rolagem
assets/js/aw-voice-reader.js  fila de fala e controles
```

Namespace `Alfamaweb\VoiceReader`, sem Composer e sem dependências.

Os prefixos `aw-voice-reader` nos assets, nos IDs de CSS e na chave da option
são anteriores ao rename e foram mantidos: trocar a chave descartaria as
configurações já salvas.

## Compatibilidade

O plugin degrada em etapas em vez de quebrar. A leitura funciona em todo
navegador moderno; o que varia é o destaque.

| Camada | Depende de | Onde funciona |
|---|---|---|
| Painel de configurações | só PHP | qualquer navegador |
| O botão aparecer | JavaScript ES2015 | qualquer navegador desde ~2017 |
| **A leitura** | `speechSynthesis` | **todos os modernos** |
| O destaque existir | `CSS.highlights` | Chrome/Edge 105+, Safari 17.2+, Firefox recente |
| Destaque palavra a palavra | evento `onboundary` | desktop sim, Android quase nunca |
| Pausar e continuar | `pause()`/`resume()` | instável no Chrome Android |

No Chrome do Android a leitura e o destaque funcionam, mas o destaque marca
o bloco em vez da palavra, porque o motor do Google raramente dispara o
`onboundary`. Sem `CSS.highlights` a leitura roda sem pintura, sem mensagem
de erro. Sem `speechSynthesis` os controles aparecem desabilitados com uma
mensagem explicando.

O CSS usa `100dvh` e `env(safe-area-inset-*)`; onde não houver suporte, o
painel fica sem teto de altura. O JavaScript não usa `?.` nem `??`, para não
exigir nada além de ES2015 do visitante.

Esta tabela vem da documentação das APIs, não de teste em aparelho. Os
navegadores que importam são os que o público do site realmente usa — veja
o Analytics antes de confiar em tabela de compatibilidade.

## Limitações conhecidas

Nenhuma impede ouvir a página; todas degradam sozinhas.

- **Sem `onboundary`** o destaque marca o bloco em vez da palavra. Comum no
  Android.
- **Sem `CSS.highlights`** a leitura funciona sem destaque.
- **A voz escolhida costuma depender de rede**, porque as locais em português
  são antigas. Demora um instante a mais e para se a conexão cair.
- **Pausar e continuar são instáveis no Chrome para Android.**
- **Não lê** imagens, canvas, iframes nem Shadow DOM.
- **Pop-ups sem semântica reconhecível** podem ser lidos; use o atributo de
  exclusão.
- **Se a página mudar durante a leitura**, o destaque é limpo e a fala segue.
- **A síntese é global ao documento.** Outro componente que fale ao mesmo
  tempo pode ser afetado por pause, resume ou cancel.

## Antes de publicar uma versão

- [ ] Ouvir uma página longa; pausar, continuar, parar e reiniciar.
- [ ] Confirmar destaque de palavra e frase, e a rolagem acompanhando.
- [ ] Confirmar que menu, rodapé, widgets e modais não são lidos.
- [ ] Testar página sem `main`, `.entry-content` nem `article`.
- [ ] Abrir o painel por teclado, fechar com Escape, conferir o anel de foco.
- [ ] Salvar os sete campos e conferir o efeito no site.
- [ ] Conferir a posição com os outros botões flutuantes ativos.
- [ ] Testar em Android: destaque em bloco, pausar e continuar.
- [ ] Testar nos navegadores que o público do site usa.

## Changelog

**1.3.0** — Renomeado para Acessibilidade Voz AW, preservando a chave da
option. Corrige o primeiro bloco sair sem destaque no Android e o mapa não
ser liberado ao fim da leitura. Anel de foco deixa de ser preto. Voz
escolhida uma vez por leitura, e desempatada por nome em vez de pela ordem do
navegador. Simplificações verificadas por comparação com a versão anterior.

**1.2.2** — Deixa de priorizar vozes locais, que no Windows são as SAPI
antigas, ignorando as neurais em rede.

**1.2.1** — Link **Configurações** na tela de plugins e campo **Idioma da
leitura**.

**1.2.0** — Destaque de frase e palavra, rolagem com `prefers-reduced-motion`,
botão circular, painel de duas para seis configurações, JavaScript em três
módulos.

**1.1.1** — Corrige a leitura em sites com OneTap: a classe no `body` casava
com a exclusão do widget e descartava a página inteira.

**1.1.0** — Classes com namespace, idioma seguindo o WordPress, nome acessível
do botão (WCAG 2.5.3), confirmação de gravação, `uninstall.php`, e correção de
laço infinito com par UTF-16 quebrado.

**1.0.0** — Versão inicial.
