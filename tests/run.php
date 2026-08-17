<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Ecole2Nat\Coach\CoachAccessRepository;
use Ecole2Nat\Coach\CoachAccessService;
use Ecole2Nat\Support\GroupScheduleParser;
use Ecole2Nat\Support\ScheduleDurationCalculator;
use Ecole2Nat\Synchronization\WorkbookReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class FakeCoachAccessRepository extends CoachAccessRepository
{
    public array $titular = [];
    public array $substitutions = [];

    public function isTitular(int $userId, int $groupId): bool
    {
        return in_array($userId . ':' . $groupId, $this->titular, true);
    }

    public function isSubstitute(int $userId, int $groupId, string $date): bool
    {
        return in_array($userId . ':' . $groupId . ':' . $date, $this->substitutions, true);
    }
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

function accessService(array $caps, array $titular = [], array $substitutions = []): CoachAccessService
{
    $GLOBALS['e2n_test_caps'] = $caps;
    $repository = new FakeCoachAccessRepository();
    $repository->titular = $titular;
    $repository->substitutions = $substitutions;
    return new CoachAccessService($repository);
}

$service = accessService(['manage_options']);
expectSame(true, $service->canPrepareGroup(4, '2020-01-01'), 'Un administrateur peut préparer toute date');
expectSame(true, $service->canOperateGroup(4, '2020-01-01'), 'Un administrateur peut opérer toute date');

$service = accessService([]);
expectSame(false, $service->canView(), 'Un utilisateur sans capacité ne voit pas le portail');
expectSame(false, $service->canPrepareGroup(4, '2026-08-17'), 'Un utilisateur sans capacité ne prépare pas');

$service = accessService(['e2n_coach_access'], ['10:4']);
expectSame(false, $service->canPrepareGroup(4, '2026-08-16'), 'Un titulaire ne modifie pas une date passée');
expectSame(false, $service->canOperateGroup(4, '2026-08-16'), 'Un titulaire ne dispose pas des droits terrain dans le passé');
expectSame(true, $service->canPrepareGroup(4, '2026-08-17'), 'Un titulaire peut préparer le jour courant');
expectSame(true, $service->canOperateGroup(4, '2026-08-17'), 'Un titulaire dispose des droits terrain le jour courant');
expectSame(true, $service->canPrepareGroup(4, '2026-08-18'), 'Un titulaire peut préparer une date future');
expectSame(false, $service->canOperateGroup(4, '2026-08-18'), 'Un titulaire ne dispose pas des droits terrain dans le futur');
expectSame('Consultation · date passée', $service->accessLabel(4, '2026-08-16'), 'Libellé titulaire passé');
expectSame('Titulaire · édition autorisée aujourd’hui', $service->accessLabel(4, '2026-08-17'), 'Libellé titulaire du jour');
expectSame('Titulaire · préparation autorisée', $service->accessLabel(4, '2026-08-18'), 'Libellé titulaire futur');

$service = accessService(['e2n_coach_access'], [], ['10:4:2026-08-17', '10:4:2026-08-18', '10:4:2026-08-16']);
expectSame(true, $service->canPrepareGroup(4, '2026-08-18'), 'Un remplaçant futur peut préparer');
expectSame(false, $service->canOperateGroup(4, '2026-08-18'), 'Un remplaçant futur ne peut pas opérer');
expectSame(true, $service->canPrepareGroup(4, '2026-08-17'), 'Un remplaçant du jour peut préparer');
expectSame(true, $service->canOperateGroup(4, '2026-08-17'), 'Un remplaçant du jour peut opérer');
expectSame(false, $service->canPrepareGroup(4, '2026-08-16'), 'Un remplacement passé expire');
expectSame('Remplaçant prévu · préparation autorisée', $service->accessLabel(4, '2026-08-18'), 'Libellé remplacement futur');
expectSame('Remplaçant · édition autorisée aujourd’hui', $service->accessLabel(4, '2026-08-17'), 'Libellé remplacement du jour');

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
$spreadsheet->createSheet()->setTitle('Inscriptions')->fromArray([['Nom', 'Prénom']]);
$spreadsheet->createSheet()->setTitle('Référentiel')->fromArray([['Catégorie', 'Domaine', 'Compétence']]);
$temporaryBase = tempnam(sys_get_temp_dir(), 'e2n-tests-');
if ($temporaryBase === false) throw new RuntimeException('Impossible de créer le fichier de test temporaire.');
$workbookPath = $temporaryBase . '.xlsx';
unlink($temporaryBase);
(new Xlsx($spreadsheet))->save($workbookPath);
$workbook = (new WorkbookReader())->read($workbookPath);
expectSame([], $workbook['errors'], 'Classeur minimal avec durée valide');
expectSame(45, $workbook['data']['groups'][0]['durationMinutes'] ?? null, 'Lecture de Durée (min)');
expectSame('17:15:00', $workbook['data']['groups'][0]['startTime'] ?? null, 'Lecture de l’heure depuis le nom');
unlink($workbookPath);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo $tests . " assertions réussies." . PHP_EOL;
