<?php
// require_once __DIR__ . '/lib/classes/clTable.php';
// require_once __DIR__ . '/lib/classes/clForm.php';
// require_once __DIR__ . '/lib/classes/clButton.php';
// require_once __DIR__ . '/lib/classes/clCard.php';
// require_once __DIR__ . '/lib/classes/clWidget.php';
// require_once __DIR__ . '/lib/classes/clGraph.php';
// require_once __DIR__ . '/lib/classes/clGrid.php';


require_once __DIR__ . '/autoloader.php';
// echo '<div>';
//     echo '<div class="col-md-6">';

//     echo (new CLTable())
//         ->theme('boxed')              // tabella con contorni
//         ->start(['id' => 'utenti'])   // puoi anche mettere 'class'=>'table-responsive'
//         ->header(['ID','Nome','Email','Stato'], [], ['scope'=>'col'])
//         ->row([1,'Mario Rossi','mario@example.com',['text'=>'<span class="badge">Attivo</span>','raw'=>true]])
//         ->row([2,'Lucia Bianchi','lucia@example.com',['text'=>'<span class="badge badge-warn">In attesa</span>','raw'=>true]])
//         ->render();

//     echo '</div>';
// echo '</div>';
// echo '<br>';

// echo '<div>';
//     echo '<div class="col-md-6">';
// // Form di contatto base (CSS integrato)
// echo (new CLForm())
//     ->start('/contact/submit', 'POST', ['id' => 'contact-form'])
//     ->csrf('_token', 'ABC123')
//     ->text('name', 'Nome', ['placeholder'=>'Mario Rossi', 'required'=>true])
//     ->email('email', 'Email', ['required'=>true, 'help'=>'Non condivideremo la tua email.'])
//     ->tel('phone', 'Telefono', ['placeholder'=>'+39 ...'])
//     ->textarea('message', 'Messaggio', ['rows'=>5, 'required'=>true])
//     ->checkbox('privacy', 'Accetto la privacy', ['required'=>true])
//     ->submit('Invia')
//     ->reset('Annulla')
//     ->render();
//     echo '</div>';
// echo '</div>';

// echo '<br>';

// echo '<div>';
//     echo '<div class="col-md-6">';

//     // Form profilo con select, radio e file (con method spoof PUT)
// echo (new CLForm())
//     ->start('/profile/42', 'POST')
//     ->methodSpoof('PUT')
//     ->text('first_name','Nome', ['value'=>'Luca','required'=>true])
//     ->text('last_name','Cognome', ['value'=>'Bianchi','required'=>true])
//     ->select('country','Paese', [
//         ['label'=>'Europa','options'=>['it'=>'Italia','fr'=>'Francia','de'=>'Germania']],
//         ['label'=>'Americhe','options'=>['us'=>'USA','br'=>'Brasile']]
//     ], ['placeholder'=>'Seleziona Paese', 'value'=>'it'])
//     ->radioGroup('gender','Genere', ['m'=>'Maschio','f'=>'Femmina','x'=>'Altro'], ['value'=>'x', 'inline'=>true])
//     ->file('avatar','Avatar', ['help'=>'PNG/JPG max 2MB'])
//     ->submit('Salva modifiche')
//     ->render();

//     echo '</div>';
// echo '</div>';

// echo '<br>';

// // Disattivare CSS integrato e usare il tuo (facoltativo)
// echo (new CLForm())
//     ->noDefaultCss()
//     ->start('/login','POST',['class'=>'form form--login'])
//     ->email('email','Email',['required'=>true])
//     ->password('password','Password',['required'=>true])
//     ->submit('Entra',['class'=>'btn btn-primary'])
//     ->render();



// echo '<br>';
// echo '<br>';
// echo '<br>';
// echo '<br>';
// echo '<br>';
// echo '<br>';
// echo '<br>';

// // 1) Pulsante singolo veloce
// echo (new CLButton())->single('Salva', [
//     'variant' => 'primary',  // default
//     'size'    => 'md',       // sm | md | lg
// ]);

// // 2) Link-as-button con icona a sinistra e stato loading
// $iconSave = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 21h14a2 2 0 0 0 2-2V8.83a2 2 0 0 0-.59-1.41l-3.83-3.83A2 2 0 0 0 15.17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Zm7-2a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>';
// echo (new CLButton())->single('Vai al dettaglio', [
//     'as'       => 'a',
//     'href'     => '/ordini/42',
//     'variant'  => 'secondary',
//     'iconLeft' => $iconSave,
//     'loading'  => false,
// ]);

// // 3) Gruppo orizzontale merge (toolbar)
// $btns = (new CLButton())
//     ->startGroup(['merge'=>true]) // pulsanti attaccati
//     ->button('Indietro', ['variant'=>'ghost'])
//     ->submit('Salva',  ['variant'=>'success'])
//     ->button('Elimina', ['variant'=>'danger', 'attrs'=>['onclick'=>"return confirm('Eliminare?')"]])
//     ->render();
// echo $btns;

// // 4) Gruppo verticale full width
// $btns2 = (new CLButton())
//     ->startGroup(['vertical'=>true])
//     ->button('Conferma', ['full'=>true])
//     ->button('Annulla',  ['variant'=>'outline','full'=>true])
//     ->render();
// echo $btns2;



// // CARD prodotto
// $actions = (new CLButton())->single('Acquista', ['variant'=>'success']);
// echo (new CLCard())
//     ->theme('elevated')
//     ->start(['id'=>'card-prodotto'])
//     ->badge('Nuovo')
//     ->media('/img/prod-123.jpg', 'Scarpa Running', ['ratio'=>'4/3'])
//     ->header('Scarpa Pro Max', 'Drop 8mm · Mesh traspirante', $actions)
//     ->body('Ammortizzazione reattiva, suola in gomma anti-scivolo. Perfetta per training quotidiano.')
//     ->list(['Spedizione gratis', 'Reso 30 giorni', '2 anni di garanzia'])
//     ->footer((new CLButton())->single('Dettagli', ['variant'=>'outline']), true)
//     ->render();

// // WIDGET metrica vendite
// $icon = '📈';
// echo (new CLWidget())
//     ->variant('success')
//     ->start(['id'=>'w-sales'])
//     ->icon('<span style="font-size:18px;">'.$icon.'</span>')
//     ->metric('€ 12.450', 'Vendite mese', '+8.3%', 'pos')
//     ->sparkline([8,9,7,10,12,11,14,16,15,17], ['width'=>200, 'height'=>48])
//     ->progress(72, '72% target')
//     ->footerRaw((new CLButton())->startGroup(['merge'=>true])->button('Report',['variant'=>'secondary'])->button('Esporta',['variant'=>'outline'])->render())
//     ->render();





// // 1) Linea multi-serie
// echo (new CLGraph())
//   ->start(720, 380)
//   ->type('line')->grid(true, true)->legend(true, 'bottom')
//   ->addSeries('Roma',   [[1,10],[2,14],[3,13],[4,18]])
//   ->addSeries('Milano', [[1, 8],[2,12],[3,15],[4,17]])
//   ->caption('Vendite trimestrali')
//   ->render();

// // 2) Barre raggruppate (richiede categories)
// echo (new CLGraph())
//   ->start(720, 380)
//   ->type('bar')->categories(['Q1','Q2','Q3','Q4'])
//   ->addSeries('2024', [10,14,13,18])
//   ->addSeries('2025', [12,16,17,20])
//   ->legend(true,'top')->grid(false,true)
//   ->render();

// // 3) Donut
// echo (new CLGraph())
//   ->start(420, 300)->type('donut')
//   ->pieData(['Marketing'=>35,'Vendite'=>25,'R&D'=>20,'Ops'=>20])
//   ->caption('Ripartizione budget')
//   ->render();


// // layout con sidebar a sinistra su desktop
// echo (new CLGrid())
//   ->start()->container('lg')
//   ->row(['g'=>16])
//     ->colRaw(12, ['lg'=>8], '<h2>Contenuto principale</h2>')
//     ->colRaw(12, ['lg'=>4, 'lg-order'=>-1], '<aside>Sidebar (a sinistra su desktop)</aside>')
//   ->endRow()
//   ->render();

// // griglia 2x2 di card
// $cards = [];
// for ($i=1;$i<=4;$i++) {
//   $cards[] = (new CLCard())->start()->header('Card '.$i)->body('Testo')->render();
// }
// echo (new CLGrid())
//   ->start()->container('xl')
//   ->row(['g'=>24, 'align'=>'center'])
//     ->colRaw(12, ['md'=>6], $cards[0])
//     ->colRaw(12, ['md'=>6], $cards[1])
//     ->colRaw(12, ['md'=>6], $cards[2])
//     ->colRaw(12, ['md'=>6], $cards[3])
//   ->endRow()
//   ->render();


// Creo la modale
$modal = (new CLModal())
  ->start('demo-modal')           // id (facoltativo)
  ->size('md')                    // sm|md|lg|xl
  ->variant('modal')              // o ->drawer('right')
  ->title('Dettagli ordine', 'Verifica le informazioni prima di confermare.')
  ->body('<p>Contenuto della modale…</p>', true)
  ->footer((new CLButton())->startGroup(['merge'=>true])
            ->button('Annulla', ['variant'=>'ghost','attrs'=>['data-clmodal-close'=>true]])
            ->button('Conferma', ['variant'=>'primary','attrs'=>['data-clmodal-close'=>true]])
            ->render());

// Pulsante di apertura
echo $modal->triggerButton('Apri modale', ['class'=>'btn-primary']);

// Render modale (da mettere in fondo alla pagina)
echo $modal->render();


$drawer = (new CLModal())
  ->start('filters')->drawer('right')->size('lg')
  ->title('Filtri')->body('<p>Opzioni…</p>', true)
  ->footer('<button data-clmodal-close class="btn">Chiudi</button>');
echo $drawer->triggerButton('Apri filtri');
echo $drawer;



?>