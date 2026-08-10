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

    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function update(int $id, array $data): array
    {
        $updated = $this->repository->update($id, $data);

        return [
            'success' => $updated,
            'message' => $updated ? 'updated' : 'error',
        ];
    }

    public function search(SwimmerSearchCriteria $criteria): array
    {
        return [
            'items' => $this->repository->search($criteria),
            'total' => $this->repository->countSearch($criteria),
        ];
    }

}