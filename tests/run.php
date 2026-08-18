<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Ecole2Nat\Coach\CoachAccessRepository;
use Ecole2Nat\Coach\CoachAccessService;
use Ecole2Nat\Support\GroupScheduleParser;
use Ecole2Nat\Support\ScheduleDurationCalculator;
use Ecole2Nat\Support\Config;
use Ecole2Nat\Synchronization\WorkbookReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class FakeCoachAccessRepository extends CoachAccessRepository
{
}

$tests = 0;
$failures = [];

function expectSame($expected, $actual, string $label): void
{
    global $tests, $failures;
    $tests++;
    if ($expected !== $actual) {
        $failures[] = $label . ' — attendu ' . var_export($expected, true) . ', obtenu ' . var_export($actual, true);
    }
}

function accessService(array $caps): CoachAccessService
{
    $GLOBALS['e2n_test_caps'] = $caps;
    return new CoachAccessService(new FakeCoachAccessRepository());
}

$service = accessService(['manage_options']);
expectSame(true, $service->canEvaluateGroup(4), 'Un administrateur peut évaluer tout groupe');
expectSame('Les coachs', Config::parentEmailSignature(), 'Signature email par défaut');
update_option('e2n_parent_email_signature', "Les Dauphins\nÉquipe pédagogique");
expectSame("Les Dauphins\nÉquipe pédagogique", Config::parentEmailSignature(), 'Signature email personnalisée');

$service = accessService([]);
expectSame(false, $service->canView(), 'Un utilisateur sans capacité ne voit pas le portail');
expectSame(false, $service->canEvaluateGroup(4), 'Un utilisateur sans capacité ne peut pas évaluer');

$service = accessService(['e2n_coach_access']);
expectSame(true, $service->canEvaluateGroup(4), 'Un coach peut évaluer un groupe sans dépendre d’une date');
expectSame(true, $service->canEvaluateGroup(9), 'Un coach peut évaluer un groupe dont il n’est pas titulaire');

$service = accessService(['e2n_coach_access']);
expectSame(true, $service->canEvaluateGroup(4), 'Les anciennes données de remplacement n’influencent plus les droits');

expectSame(['weekday' => 1, 'start_time' => '17:15:00'], GroupScheduleParser::parse('Dauphin Lundi 17h15'), 'Analyse jour et heure avec h');
expectSame(['weekday' => 3, 'start_time' => '08:00:00'], GroupScheduleParser::parse('Némo mercredi 8:00'), 'Analyse accents et heure avec deux-points');
expectSame(['weekday' => null, 'start_time' => null], GroupScheduleParser::parse('Groupe libre'), 'Créneau absent');

$timezone = new DateTimeZone('Europe/Paris');
expectSame(45, ScheduleDurationCalculator::minutes('17:15:00', '18:00:00', $timezone), 'Durée standard');
expectSame(45, ScheduleDurationCalculator::minutes('23:30:00', '00:15:00', $timezone), 'Durée passant minuit');
expectSame(null, ScheduleDurationCalculator::minutes('17:15:00', null, $timezone), 'Horaire incomplet');
expectSame(null, ScheduleDurationCalculator::minutes('invalide', '18:00:00', $timezone), 'Horaire invalide');
expectSame('18:00:00', ScheduleDurationCalculator::endTime('17:15:00', 45, $timezone), 'Calcul de l’heure de fin');
expectSame('00:15:00', ScheduleDurationCalculator::endTime('23:30:00', 45, $timezone), 'Calcul de fin après minuit');

$spreadsheet = new Spreadsheet();
$groupsSheet = $spreadsheet->getActiveSheet();
$groupsSheet->setTitle('Catégories');
$groupsSheet->fromArray([
    ['Groupe', 'Catégorie', 'Durée (min)'],
    ['Némo Lundi 17h15', 'Némo', 45],
]);
$spreadsheet->createSheet()->setTitle('Inscriptions')->fromArray([
    ['Nom', 'Prénom', 'Catégorie', 'Créneau 1', 'Renouvellement', 'Email', 'Téléphone', 'Info médicale', 'Commentaire'],
    ['Martin', 'Léa', 'Némo', 'Lundi 17h15', 'OUI', 'parent@example.test', '06 01 02 03 04', 'Détail sensible', 'Ancienne remarque'],
    ['Durand', 'Noé', 'Dauphin', 'Mardi 18h00', '', 'ignore@example.test', '', '', ''],
    ['Petit', 'Zoé', 'Avenir', 'Mercredi 19h00', 'En attente', 'ignore2@example.test', '', '', ''],
]);
$spreadsheet->createSheet()->setTitle('Référentiel Némo')->fromArray([
    ['Domaine', 'Compétence', 'Exercices'],
    ['Immersion', 'Mettre la tête sous l’eau', 'Bulles; Passage sous une frite'],
    ['', 'Ouvrir les yeux sous l’eau', 'Ramasser un objet'],
]);
$spreadsheet->getSheetByName('Référentiel Némo')->mergeCells('A2:A3');
$spreadsheet->createSheet()->setTitle('Référentiel Dauphin')->fromArray([
    ['Domaine', 'Compétence', 'Exercices'],
    ['Propulsion', 'Se déplacer sur le ventre', 'Battements avec planche'],
]);
$temporaryBase = tempnam(sys_get_temp_dir(), 'e2n-tests-');
if ($temporaryBase === false) throw new RuntimeException('Impossible de créer le fichier de test temporaire.');
$workbookPath = $temporaryBase . '.xlsx';
unlink($temporaryBase);
(new Xlsx($spreadsheet))->save($workbookPath);
$workbook = (new WorkbookReader())->read($workbookPath);
expectSame([], $workbook['errors'], 'Classeur minimal avec durée valide');
expectSame(45, $workbook['data']['groups'][0]['durationMinutes'] ?? null, 'Lecture de Durée (min)');
expectSame('17:15:00', $workbook['data']['groups'][0]['startTime'] ?? null, 'Lecture de l’heure depuis le nom');
expectSame(1, $workbook['data']['swimmers'][0]['health_alert'] ?? null, 'Info médicale convertie en indicateur booléen');
expectSame(false, array_key_exists('medical_note', $workbook['data']['swimmers'][0] ?? []), 'Texte médical absent des données analysées');
expectSame('parent@example.test', $workbook['data']['swimmers'][0]['responsible_email'] ?? null, 'Email responsable conservé');
expectSame('06 01 02 03 04', $workbook['data']['swimmers'][0]['responsible_phone'] ?? null, 'Téléphone responsable conservé');
expectSame(1, count($workbook['data']['swimmers']), 'Inscriptions sans Renouvellement OUI ou NON ignorées');
expectSame(3, count($workbook['data']['reference']), 'Lecture de plusieurs onglets Référentiel par catégorie');
expectSame('Némo', $workbook['data']['reference'][0]['category'] ?? null, 'Catégorie déduite du nom de l’onglet Référentiel');
expectSame('Immersion', $workbook['data']['reference'][1]['domain'] ?? null, 'Domaine repris depuis une cellule fusionnée');
expectSame(false, array_key_exists('domainCode', $workbook['data']['reference'][0] ?? []), 'Code domaine absent des données analysées');
expectSame(false, array_key_exists('skillCode', $workbook['data']['reference'][0] ?? []), 'Code compétence absent des données analysées');
unlink($workbookPath);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo $tests . " assertions réussies." . PHP_EOL;
