<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

// Editorial starter vocabulary. Existing administrator edits are never overwritten.
$tags = [
    'lietuviski' => 'Lithuanian',
    'angliski' => 'English',
    'fuck-ruzia' => 'fuck ruzia',
    'svelnus' => 'Mild',
    'stiprus' => 'Strong',
    'liaudiski' => 'Traditional',
];
$entries = [
    ['velnias', 'Velnias', 'Tradicinis šūksnis susierzinus ar nustebus.', 'Velnias, ir vėl pamiršau raktus.', ['lietuviski', 'svelnus', 'liaudiski']],
    ['po-velniu', 'Po velnių', 'Susierzinimo, nusivylimo ar pasipiktinimo šūksnis.', 'Po velnių, autobusas jau nuvažiavo.', ['lietuviski', 'svelnus', 'liaudiski']],
    ['eik-po-velniu', 'Eik po velnių', 'Piktas paliepimas pasitraukti ar nustoti trukdyti.', 'Eik po velnių su savo pasiteisinimais.', ['lietuviski', 'stiprus', 'liaudiski']],
    ['rupuze', 'Rupūžė', 'Liaudiškas užgaulus kreipinys arba pykčio šūksnis.', 'Ak tu rupūže, visą arbatą išpylei.', ['lietuviski', 'svelnus', 'liaudiski']],
    ['rupus-miltai', 'Rupūs miltai', 'Žaismingas, švelnus keiksmo pakaitalas.', 'Rupūs miltai, blynas prilipo!', ['lietuviski', 'svelnus', 'liaudiski']],
    ['zalia-ruta', 'Žalia rūta', 'Švelnus, žaismingas nustebimo ar apmaudo šūksnis.', 'Žalia rūta, kiek čia sniego!', ['lietuviski', 'svelnus', 'liaudiski']],
    ['kad-tave-perkunas', 'Kad tave perkūnas', 'Senas piktas palinkėjimas, šiandien dažnai vartojamas juokais.', 'Kad tave perkūnas, kaip išgąsdinai!', ['lietuviski', 'svelnus', 'liaudiski']],
    ['kad-tave-kur', 'Kad tave kur', 'Nutrauktas keiksmas, išreiškiantis apmaudą.', 'Kad tave kur, vėl pradėjo lyti.', ['lietuviski', 'svelnus', 'liaudiski']],
    ['sudas', 'Šūdas', 'Vulgarus nesėkmės, prastos kokybės ar nepasitenkinimo įvardijimas.', 'Šūdas, failas neišsisaugojo.', ['lietuviski', 'stiprus']],
    ['sudmala', 'Šūdmala', 'Vulgarus žodis beprasmiam kalbėjimui ar niekams apibūdinti.', 'Užteks šūdmalos, imamės darbo.', ['lietuviski', 'stiprus']],
    ['sudinas', 'Šūdinas', 'Vulgarus būdvardis labai prastam dalykui apibūdinti.', 'Šiandien buvo šūdina diena.', ['lietuviski', 'stiprus']],
    ['sikna', 'Šikna', 'Vulgarus žodis sėdmenims; perkeltine prasme – sunki padėtis.', 'Jei nespėsim iki vakaro, bus šikna.', ['lietuviski', 'stiprus']],
    ['siknius', 'Šiknius', 'Vulgarus įžeidimas nemaloniai ar negarbingai besielgiančiam žmogui.', 'Pasielgei kaip tikras šiknius.', ['lietuviski', 'stiprus']],
    ['bezdalas', 'Bezdalas', 'Grubokas, dažnai juokais vartojamas žodis menkam dalykui ar pasipūtėliui.', 'Daug triukšmo, o rezultatas – bezdalas.', ['lietuviski', 'svelnus']],
    ['subine', 'Subinė', 'Grubus žodis sėdmenims; vartojamas ir pykčiui išlieti.', 'Pajudink subinę, pavėluosim.', ['lietuviski', 'stiprus']],
    ['damn', 'Damn', 'Angliškas apmaudo ar susierzinimo šūksnis, panašus į „velnias“.', 'Damn, vėl išsikrovė telefonas.', ['angliski', 'svelnus']],
    ['hell', 'Hell', 'Angliškas žodis „pragaras“, vartojamas sustiprinti klausimą ar pyktį.', 'What the hell? – Kas per velnias?', ['angliski', 'svelnus']],
    ['shit', 'Shit', 'Angliškas vulgarus šūksnis ar prasto dalyko įvardijimas.', 'Shit, pamiršau susitikimą.', ['angliski', 'stiprus']],
    ['bullshit', 'Bullshit', 'Angliškas vulgarus žodis melui, nesąmonei ar tuščiam kalbėjimui.', 'That is bullshit. – Tai visiška nesąmonė.', ['angliski', 'stiprus']],
    ['fuck', 'Fuck', 'Stiprus angliškas keiksmas, vartojamas pykčiui, nuostabai ar pabrėžimui.', 'Fuck, nespėjau į traukinį.', ['angliski', 'stiprus']],
    ['fuck-off', 'Fuck off', 'Labai grubus angliškas paliepimas atsikabinti arba išeiti.', 'Fuck off. – Atsikabink.', ['angliski', 'stiprus']],
    ['asshole', 'Asshole', 'Vulgarus angliškas įžeidimas nemaloniai ar savanaudiškai besielgiančiam žmogui.', 'Don’t be an asshole. – Nesielk kaip šiknius.', ['angliski', 'stiprus']],
    ['bastard', 'Bastard', 'Angliškas įžeidimas, dažniausiai reiškiantis niekšą.', 'You bastard. – Tu niekše.', ['angliski', 'stiprus']],
    ['crap', 'Crap', 'Švelnesnis angliškas žodis šlamštui, nesąmonei ar apmaudui išreikšti.', 'Crap, pradėjau ne nuo to galo.', ['angliski', 'svelnus']],
    ['bloody-hell', 'Bloody hell', 'Britiškas nustebimo arba susierzinimo šūksnis.', 'Bloody hell, kiek žmonių!', ['angliski', 'svelnus']],
    ['bliat', 'Bliat', 'Lietuvoje girdimas iš rusų kalbos atėjęs vulgarus pykčio ar apmaudo šūksnis.', 'Bliat, užstrigo spyna.', ['fuck-ruzia', 'stiprus']],
    ['nachui', 'Nachui', 'Iš rusų kalbos atėjęs labai grubus paliepimas pasitraukti; vartojamas ir kaip keiksmažodis.', 'Eik nachui su tokiais pasiūlymais.', ['fuck-ruzia', 'stiprus']],
    ['pizda', 'Pizda', 'Iš rusų kalbos atėjęs vulgarus žodis; perkeltine prasme reiškia nesėkmę ar sunkią padėtį.', 'Jei prarasim šitą kopiją, bus pizda.', ['fuck-ruzia', 'stiprus']],
    ['chuj', 'Chuj', 'Iš rusų kalbos atėjęs vulgarus žodis, dažnas stiprių keiksmų junginiuose.', 'Chuj ten suprasi, kas parašyta.', ['fuck-ruzia', 'stiprus']],
    ['suka', 'Suka', 'Iš rusų kalbos atėjęs grubus įžeidimas ir pykčio šūksnis.', 'Suka, ir vėl nesiseka.', ['fuck-ruzia', 'stiprus']],
];

return ['tags' => $tags, 'entries' => $entries];
