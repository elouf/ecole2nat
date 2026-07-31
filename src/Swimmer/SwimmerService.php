<?php

namespace Ecole2Nat\Swimmer;

if (!defined('ABSPATH')) {
    exit;
}

class SwimmerService
{
    private SwimmerRepository $repository;

    public function __construct()
    {
        $this->repository = new SwimmerRepository();
    }

    public function all(): array
    {
        return $this->repository->all();
    }

    public function create(array $data): array
    {
        if (
            $this->repository->exists(
                $data['last_name'],
                $data['first_name'],
                $data['birth_date'] ?: null
            )
        ) {
            return [
                'success' => false,
                'message' => 'duplicate',
            ];
        }

        $created = $this->repository->create($data);

        return [
            'success' => $created,
            'message' => $created ? 'created' : 'error',
        ];
    }

    public function toggleActive(int $id): bool
    {
        return $this->repository->toggleActive($id);
    }
}