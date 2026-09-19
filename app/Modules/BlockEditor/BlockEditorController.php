<?php

namespace App\Modules\BlockEditor;

use App\Controllers\BaseController;

final class BlockEditorController extends BaseController
{
    private BlockEditorService $service;
    private BlockRepository $repository;
    private BlockRenderer $renderer;

    public function __construct()
    {
        $this->service = new BlockEditorService();
        $this->repository = new BlockRepository();
        $this->renderer = new BlockRenderer();
    }

    public function preview(): void
    {
        $entityType = $this->service->normalizeEntityType((string)request()->post('entity_type', 'post'));
        $entityId = (int)request()->post('entity_id', 0);
        $blockType = (string)request()->post('block_type', 'text');
        $contentJson = (string)request()->post('content_json', '{}');

        if (!$this->service->isAllowedBlockType($blockType)) {
            response()->json(['status' => 'error', 'message' => 'Unsupported block type.'], 422);
        }

        if ($entityId > 0 && !$this->repository->findEntity($entityType, $entityId)) {
            response()->json(['status' => 'error', 'message' => 'Entity not found.'], 404);
        }

        $data = json_decode($contentJson, true);
        $data = is_array($data) ? $data : [];

        response()->json([
            'status' => 'success',
            'html' => $this->renderer->renderBlock([
                'type' => $blockType,
                'hidden' => false,
                'data' => $data,
            ]),
        ]);
    }

    // FIREBALL_GALLERY_MULTI_SOURCE_V1
    public function uploadImage(): void
    {
        $entityType = $this->service->normalizeEntityType(
            (string)request()->post('entity_type', 'post')
        );

        $file = new \FBL\File('image');

        if (!$file->isFile || $file->getError() !== UPLOAD_ERR_OK) {
            response()->json([
                'status' => 'error',
                'message' => return_translation('editor_gallery_upload_failed'),
            ], 422);
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
        $extension = strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));
        $maxSize = \App\Services\UploadSettings::maxFileSizeBytes();

        if ($file->getSize() <= 0 || $file->getSize() > $maxSize) {
            response()->json([
                'status' => 'error',
                'message' => return_translation('admin_files_size_error'),
            ], 422);
        }

        if (!in_array($extension, $allowedExtensions, true)) {
            response()->json([
                'status' => 'error',
                'message' => return_translation('admin_files_type_error'),
            ], 422);
        }

        try {
            (new \App\Services\SafeUploadService())->validate(
                $file->getTmpName(),
                $file->getName(),
                $file->getSize(),
                $maxSize,
                $allowedExtensions
            );

            $directory = $entityType === 'page'
                ? 'pages/gallery'
                : 'posts/gallery';

            $url = (new \App\Models\FileManager())->upload($directory, $file);

            if (!is_string($url) || $url === '') {
                throw new \RuntimeException(
                    return_translation('editor_gallery_upload_failed')
                );
            }

            response()->json([
                'status' => 'success',
                'url' => $url,
            ]);
        } catch (\RuntimeException $exception) {
            log_error_details('Block editor gallery upload failed', [
                'Entity type' => $entityType,
                'File' => $file->getName(),
                'Error' => $exception->getMessage(),
            ], $exception);

            response()->json([
                'status' => 'error',
                'message' => return_translation('editor_gallery_upload_failed'),
            ], 422);
        }
    }

    public function reorder(): void
    {
        $entityType = $this->service->normalizeEntityType((string)request()->post('entity_type', 'post'));
        $entityId = (int)request()->post('entity_id', 0);
        $order = request()->post('order', []);

        if ($entityId <= 0 || !$this->repository->findEntity($entityType, $entityId)) {
            response()->json(['status' => 'error', 'message' => 'Entity not found.'], 404);
        }

        if (!is_array($order)) {
            response()->json(['status' => 'error', 'message' => 'Invalid order payload.'], 422);
        }

        $blocks = $this->repository->getBlocks($entityType, $entityId);
        $byId = [];
        foreach ($blocks as $block) {
            $id = (string)($block['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $block;
            }
        }

        $sorted = [];
        foreach ($order as $blockId) {
            $blockId = (string)$blockId;
            if (isset($byId[$blockId])) {
                $sorted[] = $byId[$blockId];
                unset($byId[$blockId]);
            }
        }

        foreach ($byId as $block) {
            $sorted[] = $block;
        }

        $this->repository->saveBlocks($entityType, $entityId, $sorted);
        response()->json(['status' => 'success', 'blocks' => $sorted]);
    }

    public function add(): void
    {
        $entityType = $this->service->normalizeEntityType((string)request()->post('entity_type', 'post'));
        $entityId = (int)request()->post('entity_id', 0);
        $blockType = (string)request()->post('block_type', 'text');
        $position = max(0, (int)request()->post('position', PHP_INT_MAX));
        $contentJson = (string)request()->post('content_json', '{}');

        if ($entityId <= 0 || !$this->repository->findEntity($entityType, $entityId)) {
            response()->json(['status' => 'error', 'message' => 'Entity not found.'], 404);
        }

        if (!$this->service->isAllowedBlockType($blockType)) {
            response()->json(['status' => 'error', 'message' => 'Unsupported block type.'], 422);
        }

        $data = json_decode($contentJson, true);
        $blocks = $this->repository->getBlocks($entityType, $entityId);
        $block = [
            'id' => $this->makeBlockId(),
            'type' => $blockType,
            'hidden' => false,
            'data' => is_array($data) ? $data : [],
        ];

        array_splice($blocks, min($position, count($blocks)), 0, [$block]);
        $this->repository->saveBlocks($entityType, $entityId, $blocks);

        response()->json(['status' => 'success', 'block' => $block, 'blocks' => $blocks]);
    }

    public function update(): void
    {
        $entityType = $this->service->normalizeEntityType((string)request()->post('entity_type', 'post'));
        $entityId = (int)request()->post('entity_id', 0);
        $blockId = (string)request()->post('block_id', '');
        $contentJson = (string)request()->post('content_json', '{}');

        if ($entityId <= 0 || !$this->repository->findEntity($entityType, $entityId)) {
            response()->json(['status' => 'error', 'message' => 'Entity not found.'], 404);
        }

        $data = json_decode($contentJson, true);
        $blocks = $this->repository->getBlocks($entityType, $entityId);
        $updated = null;

        foreach ($blocks as &$block) {
            if ((string)($block['id'] ?? '') !== $blockId) {
                continue;
            }

            $block['data'] = is_array($data) ? $data : [];
            $updated = $block;
            break;
        }
        unset($block);

        if ($updated === null) {
            response()->json(['status' => 'error', 'message' => 'Block not found.'], 404);
        }

        $this->repository->saveBlocks($entityType, $entityId, $blocks);
        response()->json(['status' => 'success', 'block' => $updated, 'blocks' => $blocks]);
    }

    public function delete(): void
    {
        $entityType = $this->service->normalizeEntityType((string)request()->post('entity_type', 'post'));
        $entityId = (int)request()->post('entity_id', 0);
        $blockId = (string)request()->post('block_id', '');

        if ($entityId <= 0 || !$this->repository->findEntity($entityType, $entityId)) {
            response()->json(['status' => 'error', 'message' => 'Entity not found.'], 404);
        }

        $blocks = array_values(array_filter(
            $this->repository->getBlocks($entityType, $entityId),
            static fn(array $block): bool => (string)($block['id'] ?? '') !== $blockId
        ));

        $this->repository->saveBlocks($entityType, $entityId, $blocks);
        response()->json(['status' => 'success', 'blocks' => $blocks]);
    }

    public function orderModal(): void
    {
        response()->json(['status' => 'success']);
    }

    private function makeBlockId(): string
    {
        try {
            return 'block_' . bin2hex(random_bytes(8));
        } catch (\Throwable) {
            return 'block_' . str_replace('.', '', uniqid('', true));
        }
    }
}
