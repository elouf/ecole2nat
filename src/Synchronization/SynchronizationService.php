<?php

namespace Ecole2Nat\Synchronization;

if (!defined('ABSPATH')) { exit; }

final class SynchronizationService
{
    public function __construct(private ?WorkbookReader $reader=null, private ?SynchronizationRepository $repository=null)
    { $this->reader ??= new WorkbookReader(); $this->repository ??= new SynchronizationRepository(); }

    public function analyze(string $path, array $season): array
    {
        $result=$this->reader->read($path);
        $counts=['groups'=>0,'reference_rows'=>0,'swimmers'=>0,'exercises'=>0];
        if(!empty($result['data'])){
            $counts['groups']=count($result['data']['groups']);
            $counts['reference_rows']=count($result['data']['reference']);
            $counts['swimmers']=count($result['data']['swimmers']);
            foreach($result['data']['reference'] as $row)$counts['exercises']+=count($row['exercises']);
        }
        $result['counts']=$counts;
        $result['plan'] = $result['errors'] === [] ? $this->repository->estimate($result['data'], $season) : [];
        return $result;
    }

    public function synchronize(string $path,string $filename,int $userId,array $season):array
    {
        $analysis=$this->analyze($path, $season);
        if($analysis['errors']!==[]) return ['success'=>false,'stats'=>[],'errors'=>$analysis['errors']];
        return $this->repository->synchronize($analysis['data'],$userId,$filename,$season);
    }

    public function logs():array{return $this->repository->recentLogs();}
}
