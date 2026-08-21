<?php

namespace Ecole2Nat\Competition;

if (!defined('ABSPATH')) { exit; }

class CompetitionService
{
    private const EVENTS=['50PAP','100PAP','200PAP','50DOS','100DOS','200DOS','50BRASSE','100BRASSE','200BRASSE','50NL','100NL','200NL','400NL','800NL','1500NL','1004N','2004N','4004N'];
    public function __construct(private ?CompetitionRepository $repository = null)
    {
        $this->repository ??= new CompetitionRepository();
    }

    public function forSwimmer(int $swimmerId): array
    {
        return array_map(function (array $competition): array {
            $competition['registration_state'] = $this->registrationState($competition);
            return $competition;
        }, $this->repository->forSwimmer($swimmerId));
    }

    public function saveParentResponse(int $competitionId, int $swimmerId, string $response, string $comment, ?bool $parentsOfficial, string $attendanceDays): array
    {
        $competition = $this->repository->find($competitionId);
        if ($competition === null || ($competition['status'] ?? '') !== 'published' || !$this->repository->eligible($competitionId, $swimmerId) || $this->registrationState($competition) !== 'open') return ['success'=>false,'message'=>'closed'];
        if ($this->repository->isEngaged($competitionId, $swimmerId)) return ['success'=>false,'message'=>'engaged'];
        if ($parentsOfficial === null) return ['success'=>false,'message'=>'invalid'];
        $twoDays = !empty($competition['end_date']) && $competition['end_date'] !== $competition['start_date'];
        if ($response === 'yes' && $twoDays && !in_array($attendanceDays, ['both','first_day','second_day'], true)) return ['success'=>false,'message'=>'invalid'];
        if ($response !== 'yes' || !$twoDays) $attendanceDays = '';
        return $this->save($competitionId, $swimmerId, $response, $comment, 'parent', null, $parentsOfficial, $attendanceDays);
    }

    public function saveCoachResponse(int $competitionId, int $swimmerId, string $response, string $comment, int $userId): array
    {
        $competition=$this->repository->find($competitionId);
        if ($competition===null || ($competition['status']??'')!=='published' || !empty($competition['started_at']) || !$this->repository->eligible($competitionId, $swimmerId)) return ['success'=>false,'message'=>'invalid'];
        return $this->save($competitionId, $swimmerId, $response, $comment, 'coach', $userId, null, '');
    }

    private function save(int $competitionId, int $swimmerId, string $response, string $comment, string $source, ?int $userId, ?bool $parentsOfficial, string $attendanceDays): array
    {
        if (!in_array($response, ['yes','no'], true)) return ['success'=>false,'message'=>'invalid'];
        $success = $this->repository->saveResponse($competitionId,$swimmerId,$response,sanitize_textarea_field($comment),$source,$userId,$parentsOfficial,$attendanceDays);
        return ['success'=>$success,'message'=>$success?'saved':'error'];
    }

    public function coachList(): array
    {
        $rows=$this->repository->coachList();
        foreach($rows as &$row){$detail=$this->repository->coachDetail((int)$row['id']);$swimmers=$detail['swimmers']??[];$row['eligible_count']=count($swimmers);$row['yes_count']=count(array_filter($swimmers,static fn($s)=>($s['response']??'')==='yes'));$row['no_count']=count(array_filter($swimmers,static fn($s)=>($s['response']??'')==='no'));$row['pending_engagement_count']=count(array_filter($swimmers,static fn($s)=>($s['response']??'')==='yes' && empty($s['is_engaged'])));}
        unset($row);return $rows;
    }
    public function coachDetail(int $competitionId): ?array { return $this->repository->coachDetail($competitionId); }
    public function events(): array { return self::EVENTS; }
    public function unengagedYesNames(int $competitionId): array { return $this->repository->unengagedYesNames($competitionId); }
    public function start(int $competitionId,int $userId,bool $forced): array
    { $competition=$this->repository->find($competitionId);if($competition===null||($competition['status']??'')!=='published')return ['success'=>false,'message'=>'invalid'];$missing=$this->repository->unengagedYesNames($competitionId);if($missing!==[]&&!$forced)return ['success'=>false,'message'=>'missing','names'=>$missing];return ['success'=>$this->repository->start($competitionId,$userId,$forced),'message'=>'started']; }
    public function stop(int $competitionId): bool { return $this->repository->stop($competitionId); }
    public function close(int $competitionId,int $userId): bool { return $this->repository->isStarted($competitionId)&&$this->repository->close($competitionId,$userId); }
    public function resume(int $competitionId): bool { return $this->repository->isStarted($competitionId)&&$this->repository->resume($competitionId); }
    public function activeCompetitions(): array { return $this->repository->activeCompetitions(10); }
    public function participants(int $competitionId): array { return $this->repository->participants($competitionId); }
    public function availableParticipants(int $competitionId): array { return $this->repository->availableParticipants($competitionId); }
    public function addParticipant(int $competitionId,int $swimmerId,int $userId): bool { return $this->repository->isStarted($competitionId)&&$this->repository->addParticipant($competitionId,$swimmerId,$userId); }
    public function performances(int $competitionId,int $swimmerId): array { return $this->repository->performances($competitionId,$swimmerId); }
    public function savePerformance(int $competitionId,int $swimmerId,int $performanceId,array $input,int $userId): bool
    {
        $event=sanitize_key((string)($input['event_code']??''));$event=strtoupper($event);$rating=absint($input['time_rating']??0);
        if(!$this->repository->isStarted($competitionId)||!$this->repository->isParticipant($competitionId,$swimmerId)||!in_array($event,self::EVENTS,true)||$rating>5)return false;
        return $this->repository->savePerformance($competitionId,$swimmerId,$performanceId,['event_code'=>$event,'elapsed_time'=>sanitize_text_field((string)($input['elapsed_time']??'')),'comment'=>sanitize_textarea_field((string)($input['comment']??'')),'is_disqualified'=>!empty($input['is_disqualified'])?1:0,'time_rating'=>$rating>0?$rating:null],$userId);
    }
    public function deletePerformance(int $competitionId,int $swimmerId,int $performanceId): bool
    { return $performanceId>0&&$this->repository->isStarted($competitionId)&&$this->repository->isParticipant($competitionId,$swimmerId)&&$this->repository->deletePerformance($competitionId,$swimmerId,$performanceId); }
    public function setEngaged(int $competitionId, int $swimmerId, bool $engaged, int $userId): bool
    {
        $competition=$this->repository->find($competitionId);
        return $competition!==null && ($competition['status']??'')==='published' && empty($competition['started_at']) && $this->repository->eligible($competitionId,$swimmerId) && $this->repository->setEngaged($competitionId,$swimmerId,$engaged,$userId);
    }

    public function registrationState(array $competition): string
    {
        if (($competition['status'] ?? '') === 'cancelled') return 'cancelled';
        $now = current_time('mysql');
        if ($now < (string) $competition['registration_opens_at']) return 'upcoming';
        if ($now > (string) $competition['registration_closes_at']) return 'closed';
        return 'open';
    }
}
