<?php

declare(strict_types=1);

namespace RZ\Roadiz\CoreBundle\Serializer;

use RZ\TreeWalker\WalkerInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

final class TreeWalkerNormalizer implements ContextAwareNormalizerInterface, CacheableSupportsMethodInterface
{
    private ObjectNormalizer $normalizer;

    public function __construct(ObjectNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        return [
            'item' => $this->normalizer->normalize($object->getItem(), $format, $context),
            'children' => $object->getChildren()->map(function (WalkerInterface $walker) use ($format, $context) {
                return $this->normalize($walker, $format, $context);
            })->toArray(),
            'level' => $object->getCurrentLevel(),
            'childrenCount' => $object->count(),
            'index' => $object->getIndex(),
            'metadata' => $object->getAllMetadata(),
        ];
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof WalkerInterface;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === TreeWalkerNormalizer::class;
    }
}
