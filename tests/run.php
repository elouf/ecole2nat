<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Ecole2Nat\Coach\CoachAccessRepository;
use Ecole2Nat\Coach\CoachAccessService;
use Ecole2Nat\Competition\CompetitionService;
use Ecole2Nat\Competition\CompetitionRepository;
use Ecole2Nat\ParentPortal\ParentAccessRepository;
use Ecole2Nat\ParentPortal\ParentAccessService;
use Ecole2Nat\Performance\PerformanceRepository;
use Ecole2Nat\Performance\PerformanceService;
use Ecole2Nat\Support\GroupScheduleParser;
use Ecole2Nat\Support\ScheduleDurationCalculator;
use Ecole2Nat\Support\Config;
use Ecole2Nat\Support\ContactList;
use Ecole2Nat\Support\Extranat;
use Ecole2Nat\Synchronization\WorkbookReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class FakeCoachAccessRepository extends CoachAccessRepository
{
}

final class FakePerformanceRepository extends PerformanceRepository
{
    public array $saved = [];

    public function saveTrainingTimed(int $groupId, int $seasonId, int $swimmerId, int $performanceId, array $data, int $userId): int
    {
        $this->saved = compact('groupId', 'seasonId', 'swimmerId', 'performanceId', 'data', 'userId');
        return 123;
    }
    public function deleteTrainingPerformance(int $groupId,int $seasonId,int $swimmerId,int $performanceId):bool
    { $this->saved=compact('groupId','seasonId','swimmerId','performanceId');return true; }
    public function trainingSeriesGroups(string $seriesKey):array{return [4,9];}
    public function deleteTrainingSeries(string $seriesKey):int{$this->saved=compact('seriesKey');return 2;}
    public function deleteForSwimmer(string $source,int $swimmerId,int $performanceId):bool
    { $this->saved=compact('source','swimmerId','performanceId');return true; }
    public function purgeForSwimmer(int $swimmerId):bool
    { $this->saved=compact('swimmerId');return true; }
    public function countsForSwimmers(array $swimmerIds):array{return [42=>3,57=>1];}
    public function competitionCountsForSwimmers(int $competitionId,array $swimmerIds):array{return $competitionId===8?[42=>2]:[];}
}

final class FakeParentAccessRepository extends ParentAccessRepository
{
    public array $swimmer = [
        'id' => 42,
        'first_name' => 'Élise-Marie',
        'last_name' => 'Martin',
        'birth_date' => '2012-04-03',
        'is_active' => 1,
    ];
    public array $birthCandidates = [];

    public function findSwimmer(int $swimmerId): ?array { return $swimmerId === 42 ? $this->swimmer : null; }
    public function activeSwimmersBornOn(string $birthDate): array
    { return $birthDate === '2012-04-03' ? ($this->birthCandidates ?: [$this->swimmer]) : []; }
    public function markUsed(int $swimmerId): void {}
    public function logAttempt(?int $swimmerId, bool $success, string $ipHash): void {}
}

final class FakeCompetitionRepository extends CompetitionRepository
{
    public array $saved = [];
    public bool $engaged = false;
    public bool $started = true;
    public bool $participant = true;

    public function forSwimmer(int $swimmerId): array
    {
        return [[
            'id' => 1,
            'status' => 'published',
            'start_date' => '2026-10-10',
            'registration_opens_at' => '2026-09-25 00:00:00',
            'registration_closes_at' => '2026-10-04 23:59:59',
        ]];
    }

    public function find(int $competitionId): ?array
    {
        return ['id'=>$competitionId,'status'=>'published','start_date'=>'2026-08-22','end_date'=>'2026-08-23','registration_opens_at'=>'2026-08-10 00:00:00','registration_closes_at'=>'2026-08-20 23:59:59'];
    }
    public function eligible(int $competitionId,int $swimmerId):bool{return true;}
    public function isEngaged(int $competitionId,int $swimmerId):bool{return $this->engaged;}
    public function isStarted(int $competitionId):bool{return $this->started;}
    public function isParticipant(int $competitionId,int $swimmerId):bool{return $this->participant;}
    public function savePerformance(int $competitionId,int $swimmerId,int $performanceId,array $data,int $userId):bool
    { $this->saved=$data+compact('competitionId','swimmerId','performanceId','userId');return true; }
    public function saveTimedPerformance(int $competitionId,int $swimmerId,int $performanceId,array $data,int $userId):int
    { $this->saved=$data+compact('competitionId','swimmerId','performanceId','userId');return $performanceId>0?$performanceId:91; }
    public function deletePerformance(int $competitionId,int $swimmerId,int $performanceId):bool
    { $this->saved=compact('competitionId','swimmerId','performanceId');return true; }
    public function deleteSeries(int $competitionId,string $seriesKey):int
    { $this->saved=compact('competitionId','seriesKey');return 2; }
    public function saveResponse(int $competitionId,int $swimmerId,string $response,string $comment,string $source,?int $userId,?bool $parentsOfficial=null,?string $attendanceDays=null):bool
    { $this->saved=compact('competitionId','swimmerId','response','comment','source','userId','parentsOfficial','attendanceDays');return true; }
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

function expectNotContains(string $needle, string $haystack, string $label): void
{
    global $tests, $failures;
    $tests++;
    if (str_contains(strtolower($haystack), strtolower($needle))) {
        $failures[] = $label . ' — motif interdit trouvé : ' . $needle;
    }
}

$sqlSources = [
    __DIR__ . '/../src/Coach/CoachPortalRepository.php',
    __DIR__ . '/../src/ParentPortal/ParentAccessRepository.php',
    __DIR__ . '/../src/Database/Installer.php',
];
foreach ($sqlSources as $sqlSource) {
    $contents = file_get_contents($sqlSource);
    expectSame(true, is_string($contents), 'Lecture de ' . basename($sqlSource));
    expectNotContains('} groups ', (string) $contents, 'Aucun alias SQL réservé groups dans ' . basename($sqlSource));
}

$bootstrapSource = file_get_contents(__DIR__ . '/../ecole2nat.php');
expectSame(true, is_string($bootstrapSource), 'Lecture du point d’entrée du plugin');
expectSame(
    true,
    str_contains((string) $bootstrapSource, "add_action('init', ['\\Ecole2Nat\\Database\\Installer', 'maybeUpgrade'], 1)"),
    'Les migrations automatiques attendent le hook init'
);

$coachScript = file_get_contents(__DIR__ . '/../assets/js/coach-portal.js');
expectSame(true, is_string($coachScript), 'Lecture du script Coach');
$searchListenerPosition = strpos((string) $coachScript, "field.matches('[data-e2n-swimmer-search]')");
$ajaxGuardPosition = strpos((string) $coachScript, "typeof e2nCoachAjax === 'undefined'");
expectSame(
    true,
    $searchListenerPosition !== false && $ajaxGuardPosition !== false && $searchListenerPosition < $ajaxGuardPosition,
    'La recherche Nageurs est initialisée avant la garde AJAX'
);
$coachStyles = file_get_contents(__DIR__ . '/../assets/css/coach-portal.css');
expectSame(true, is_string($coachStyles), 'Lecture des styles Coach');
expectSame(
    true,
    str_contains((string) $coachStyles, '.e2n-coach [hidden]{display:none!important}'),
    'Les cartes filtrées restent masquées malgré leurs règles de mise en page'
);
$coachPortalSource = file_get_contents(__DIR__ . '/../src/Coach/CoachPortal.php');
$evaluationServiceSource = file_get_contents(__DIR__ . '/../src/Evaluation/EvaluationService.php');
expectSame(true, str_contains((string) $coachPortalSource, 'e2n-collective-note'), 'L’évaluation collective propose un commentaire par nageur');
expectSame(true, str_contains((string) $evaluationServiceSource, '$sw[\'notes\']'), 'Les notes existantes sont chargées dans l’évaluation collective');

$service = accessService(['manage_options']);
expectSame(true, $service->canEvaluateGroup(4), 'Un administrateur peut évaluer tout groupe');
expectSame('Les coachs', Config::parentEmailSignature(), 'Signature email par défaut');
expectSame('6.00', Config::invoiceMealPrice(), 'Prix par défaut d’un repas');
expectSame('20.00', Config::invoiceNightPrice(), 'Prix par défaut d’une nuitée');
expectSame('Dauphins de Mayenne', Config::invoiceIssuerName(), 'Nom par défaut de l’émetteur des factures');
update_option('e2n_parent_email_signature', "Les Dauphins\nÉquipe pédagogique");
expectSame("Les Dauphins\nÉquipe pédagogique", Config::parentEmailSignature(), 'Signature email personnalisée');
expectSame('a@example.test / b@example.test', ContactList::normalizeEmails('a@example.test;b@example.test'), 'Emails séparés par point-virgule normalisés');
expectSame('06 00 00 00 00 / 07 00 00 00 00', ContactList::normalizePhones("06 00 00 00 00\n07 00 00 00 00"), 'Téléphones séparés par retour ligne normalisés');
expectSame('https://ffn.extranat.fr/webffn/nat_recherche.php?idact=nat&idbas=25&idrch_id=4057481', Extranat::swimmerUrl('4057481'), 'URL de la fiche Extranat construite avec la licence');
expectSame('', Extranat::swimmerUrl(''), 'Absence de lien Extranat sans licence');

$service = accessService([]);
expectSame(false, $service->canView(), 'Un utilisateur sans capacité ne voit pas le portail');
expectSame(false, $service->canEvaluateGroup(4), 'Un utilisateur sans capacité ne peut pas évaluer');

$service = accessService(['e2n_coach_access']);
expectSame(true, $service->canEvaluateGroup(4), 'Un coach peut évaluer un groupe sans dépendre d’une date');
expectSame(true, $service->canEvaluateGroup(9), 'Un coach peut évaluer un groupe dont il n’est pas titulaire');

$parentRepository = new FakeParentAccessRepository();
$parentService = new ParentAccessService($parentRepository);
$firstParentCode = $parentService->permanentCode(42);
$sameParentCode = $parentService->permanentCode(42);
expectSame(true, $firstParentCode['success'], 'Calcul du code Parents déterministe');
expectSame('ELISEMARIE03042012', $firstParentCode['code'], 'Le code normalise le prénom et ajoute la naissance au format JJMMAAAA');
expectSame($firstParentCode['code'], $sameParentCode['code'], 'Le code Parents reste identique à chaque calcul');
$resetParentCode = $parentService->resetCode(42);
expectSame($firstParentCode['code'], $resetParentCode['code'], 'Le code déterministe ne possède plus de réinitialisation aléatoire');
expectSame(true, $parentService->authenticate('élise-marie 03/04/2012')['success'], 'La connexion accepte une saisie avec accents et séparateurs');
$parentRepository->birthCandidates = [$parentRepository->swimmer, array_merge($parentRepository->swimmer, ['id' => 57])];
expectSame('ambiguous_code', $parentService->authenticate('ELISEMARIE03042012')['message'], 'Une collision de codes est refusée explicitement');

$competitionService = new CompetitionService();
expectSame('upcoming', $competitionService->registrationState(['status'=>'published','registration_opens_at'=>'2026-08-18 00:00:00','registration_closes_at'=>'2026-08-20 23:59:59']), 'Inscriptions futures non modifiables');
expectSame('open', $competitionService->registrationState(['status'=>'published','registration_opens_at'=>'2026-08-10 00:00:00','registration_closes_at'=>'2026-08-20 23:59:59']), 'Inscriptions ouvertes dans la période');
expectSame('closed', $competitionService->registrationState(['status'=>'published','registration_opens_at'=>'2026-08-01 00:00:00','registration_closes_at'=>'2026-08-16 23:59:59']), 'Inscriptions closes après échéance');
expectSame('cancelled', $competitionService->registrationState(['status'=>'cancelled','registration_opens_at'=>'2026-08-10 00:00:00','registration_closes_at'=>'2026-08-20 23:59:59']), 'Compétition annulée prioritaire sur la période');
$upcomingCompetitions = (new CompetitionService(new FakeCompetitionRepository()))->forSwimmer(42);
expectSame(1, count($upcomingCompetitions), 'Une compétition publiée reste visible avant l’ouverture des inscriptions');
expectSame('upcoming', $upcomingCompetitions[0]['registration_state'] ?? null, 'Une compétition visible avant ouverture reste non modifiable');
$competitionRepository = new FakeCompetitionRepository();
$competitionResponseService = new CompetitionService($competitionRepository);
expectSame('invalid', $competitionResponseService->saveParentResponse(1,42,'yes','',true,'')['message'], 'Le choix des jours est obligatoire pour une participation sur deux jours');
expectSame('saved', $competitionResponseService->saveParentResponse(1,42,'yes','Note',true,'first_day')['message'], 'Une réponse complète sur deux jours est enregistrée');
expectSame(true, $competitionRepository->saved['parentsOfficial'] ?? null, 'La participation des parents comme officiels est conservée');
expectSame('first_day', $competitionRepository->saved['attendanceDays'] ?? null, 'Le premier jour choisi est conservé');
expectSame('saved', $competitionResponseService->saveParentResponse(1,42,'no','',false,'both')['message'], 'Un refus reste enregistrable sans choix de jours');
expectSame('', $competitionRepository->saved['attendanceDays'] ?? null, 'Un refus efface le choix de jours devenu sans objet');
$competitionRepository->engaged = true;
expectSame('engaged', $competitionResponseService->saveParentResponse(1,42,'no','',false,'')['message'], 'Une réponse parent est verrouillée après validation Extranat');
$competitionRepository->engaged = false;
expectSame(22, count($competitionResponseService->events()), 'Le référentiel terrain expose les 22 épreuves attendues');
expectSame(true, $competitionResponseService->savePerformance(1,42,0,['event_code'=>'100NL','elapsed_time'=>'1:02.34','comment'=>'Bonne course','time_rating'=>4],7), 'Une performance valide est enregistrée');
expectSame('100NL', $competitionRepository->saved['event_code'] ?? null, 'Le code épreuve est normalisé et conservé');
expectSame(true, $competitionResponseService->savePerformance(1,42,0,['event_code'=>'25NL','elapsed_time'=>'0:16.42','time_rating'=>4],7), 'Une épreuve de 25 mètres est acceptée en compétition');
expectSame(false, $competitionResponseService->savePerformance(1,42,0,['event_code'=>'25FLY','time_rating'=>4],7), 'Une épreuve inconnue est refusée');
expectSame(false, $competitionResponseService->savePerformance(1,42,0,['event_code'=>'100NL','time_rating'=>6],7), 'Une appréciation supérieure à cinq est refusée');
$timed=$competitionResponseService->saveTimedPerformance(1,42,0,['event_code'=>'100NL','elapsed_time'=>'1:02.34','comment'=>'Série','time_rating'=>5,'series_key'=>'series-test-123'],7);
expectSame(true, $timed['success'], 'Un arrêt chronométré valide crée immédiatement une performance');
expectSame(91, $timed['performance_id'], 'L’identifiant de la performance chronométrée est retourné au portail');
expectSame(false, $competitionResponseService->saveTimedPerformance(1,42,0,['event_code'=>'100NL','elapsed_time'=>'62 secondes','series_key'=>'series-test-123'],7)['success'], 'Un chrono forgé au mauvais format est refusé');
$competitionRepository->participant = false;
expectSame(false, $competitionResponseService->savePerformance(1,42,0,['event_code'=>'100NL','time_rating'=>4],7), 'Un non-participant ne peut pas recevoir de performance');
$competitionRepository->participant = true;
expectSame(true, $competitionResponseService->deletePerformance(1,42,9), 'Une performance appartenant au participant peut être supprimée');
expectSame(9, $competitionRepository->saved['performanceId'] ?? null, 'La suppression cible la performance demandée');
expectSame(true, $competitionResponseService->deleteSeries(1,'series-test-123'), 'Une série de compétition identifiée peut être supprimée');

$trainingRepository = new FakePerformanceRepository();
$trainingService = new PerformanceService($trainingRepository);
expectSame([42=>3,57=>1],$trainingService->countsForSwimmers([42,57]),'Les compteurs de chronos sont exposés aux listes du portail Coach');
expectSame([42=>2],$trainingService->competitionCountsForSwimmers(8,[42,57]),'Le compteur d’une compétition est limité aux épreuves de cette compétition');
$trainingResult = $trainingService->saveTrainingTimed(4, 2, 42, 0, ['event_code' => '25pap', 'elapsed_time' => '0:18.37', 'comment' => 'Départ à travailler', 'time_rating' => 3, 'series_key' => 'series-test-456'], 7);
expectSame(true, $trainingResult['success'], 'Un chrono d’entraînement valide est conservé');
expectSame('25PAP', $trainingRepository->saved['data']['event_code'] ?? null, 'Le code d’épreuve d’entraînement est normalisé');
expectSame(4, $trainingRepository->saved['groupId'] ?? null, 'Le chrono d’entraînement conserve son groupe');
expectSame(false, $trainingService->saveTrainingTimed(4, 2, 42, 0, ['event_code' => '25PAP', 'elapsed_time' => '18 secondes', 'series_key' => 'series-test-456'], 7)['success'], 'Un chrono d’entraînement mal formé est refusé');
expectSame(true, $trainingService->deleteTrainingPerformance(4,2,42,123), 'Un chrono d’entraînement identifié peut être supprimé');
expectSame([4,9], $trainingService->trainingSeriesGroups('series-test-456'), 'Les groupes d’une série mixte sont retrouvés avant suppression');
expectSame(true, $trainingService->deleteTrainingSeries('series-test-456'), 'Toute une série d’entraînement peut être supprimée');
expectSame(true, $trainingService->deleteForSwimmer('competition',42,91), 'Un chrono de compétition peut être supprimé depuis la fiche nageur');
expectSame(['source'=>'competition','swimmerId'=>42,'performanceId'=>91], $trainingRepository->saved, 'La suppression unitaire conserve sa source et détruit la ligne ciblée');
expectSame(false, $trainingService->deleteForSwimmer('unknown',42,91), 'Une source de chrono inconnue est refusée');
expectSame(true, $trainingService->purgeForSwimmer(42), 'Tous les chronos du nageur peuvent être purgés');
expectSame(['swimmerId'=>42], $trainingRepository->saved, 'La purge cible uniquement le nageur demandé');

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
    ['Nom', 'Prénom', 'Catégorie', 'Créneau 1', 'Renouvellement', 'Email', 'Téléphone', 'Info médicale', 'Commentaire', 'Compétition'],
    ['Martin', 'Léa', 'Némo', 'Lundi 17h15', 'OUI', 'parent@example.test / second@example.test', '06 01 02 03 04 / 07 05 06 07 08', 'Détail sensible', 'Ancienne remarque', 'U11;u11;HANDI'],
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
$spreadsheet->createSheet()->setTitle('Compétitions')->fromArray([
    ['Code compétition','Nom','Date début','Date fin','Lieu','Bassin','Début inscriptions','Fin inscriptions','Catégories de compétiteurs','Fiche technique','Programme','Covoiturage','liveFFN','Album photo','Informations','Statut'],
    ['MEETING-2026','Meeting de rentrée','2026-09-20','2026-09-20','Brest','25m','2026-08-20','2026-09-10','U11;HANDI','https://example.test/meeting.pdf','https://example.test/programme.pdf','https://example.test/covoiturage','https://example.test/live','https://example.test/photos','Prévoir le pique-nique','Publiée'],
    ['INTERNE-2026','Compétition interne','2026-10-10','2026-10-10','Brest','50m','2026-09-01','2026-10-01','TOUS','','','','','','','','Brouillon'],
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
expectSame('parent@example.test / second@example.test', $workbook['data']['swimmers'][0]['responsible_email'] ?? null, 'Plusieurs emails responsables conservés séparément');
expectSame('06 01 02 03 04 / 07 05 06 07 08', $workbook['data']['swimmers'][0]['responsible_phone'] ?? null, 'Plusieurs téléphones responsables conservés séparément');
expectSame(['U11', 'HANDI'], $workbook['data']['swimmers'][0]['competition_categories'] ?? null, 'Catégories de compétiteur multiples lues depuis les inscriptions');
expectSame(1, count($workbook['data']['swimmers']), 'Inscriptions sans Renouvellement OUI ou NON ignorées');
expectSame(3, count($workbook['data']['reference']), 'Lecture de plusieurs onglets Référentiel par catégorie');
expectSame('Némo', $workbook['data']['reference'][0]['category'] ?? null, 'Catégorie déduite du nom de l’onglet Référentiel');
expectSame('Immersion', $workbook['data']['reference'][1]['domain'] ?? null, 'Domaine repris depuis une cellule fusionnée');
expectSame(false, array_key_exists('domainCode', $workbook['data']['reference'][0] ?? []), 'Code domaine absent des données analysées');
expectSame(false, array_key_exists('skillCode', $workbook['data']['reference'][0] ?? []), 'Code compétence absent des données analysées');
expectSame('MEETING-2026', $workbook['data']['competitions'][0]['code'] ?? null, 'Lecture du code stable de compétition');
expectSame('25m', $workbook['data']['competitions'][0]['pool_length'] ?? null, 'Lecture de la longueur du bassin de compétition');
expectSame('https://example.test/programme.pdf', $workbook['data']['competitions'][0]['program_url'] ?? null, 'Lecture du lien vers le programme');
expectSame('https://example.test/covoiturage', $workbook['data']['competitions'][0]['carpool_url'] ?? null, 'Lecture du lien de covoiturage');
expectSame('https://example.test/live', $workbook['data']['competitions'][0]['liveffn_url'] ?? null, 'Lecture du lien liveFFN');
expectSame('https://example.test/photos', $workbook['data']['competitions'][0]['photo_album_url'] ?? null, 'Lecture du lien vers l’album photo');
expectSame(['U11', 'HANDI'], $workbook['data']['competitions'][0]['competition_categories'] ?? null, 'Lecture de plusieurs catégories de compétiteur ciblées');
expectSame(0, $workbook['data']['competitions'][0]['target_all'] ?? null, 'Une liste de catégories ne cible pas tous les nageurs');
expectSame('2026-09-10 23:59:59', $workbook['data']['competitions'][0]['registration_closes_at'] ?? null, 'Fin des inscriptions incluse jusqu’à la fin de journée');
expectSame(1, $workbook['data']['competitions'][1]['target_all'] ?? null, 'TOUS cible toute la saison');
$spreadsheet->getSheetByName('Compétitions')->setCellValue('F2','33m');
(new Xlsx($spreadsheet))->save($workbookPath);
$invalidPoolWorkbook=(new WorkbookReader())->read($workbookPath);
expectSame(true, count(array_filter($invalidPoolWorkbook['errors'], static fn(string $error):bool=>str_contains($error,'bassin doit être 25m ou 50m')))>0, 'Une longueur de bassin inconnue est refusée');
unlink($workbookPath);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo $tests . " assertions réussies." . PHP_EOL;
