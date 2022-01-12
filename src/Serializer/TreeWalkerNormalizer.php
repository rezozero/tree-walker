<?php

declare(strict_types=1);

namespace RZ\TreeWalker\Serializer;

use RZ\TreeWalker\WalkerInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class TreeWalkerNormalizer implements ContextAwareNormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    /**
     * @var DenormalizerInterface&NormalizerInterface
     */
    private $decorated;

    public function __construct(NormalizerInterface $decorated)
    {
        if (!$decorated instanceof DenormalizerInterface) {
            throw new \InvalidArgumentException(sprintf('The decorated normalizer must implement the %s.', DenormalizerInterface::class));
        }
        $this->decorated = $decorated;
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        if ($object instanceof WalkerInterface) {
            $type = explode('\\', get_class($object));
            return [
                '@type' => end($type),
                'item' => $this->decorated->normalize($object->getItem(), $format, $context),
                'children' => $object->getChildren()->map(function (WalkerInterface $walker) use ($format, $context) {
                    return $this->normalize($walker, $format, $context);
                })->toArray(),
                'level' => $object->getCurrentLevel(),
                'childrenCount' => $object->count(),
                'index' => $object->getIndex(),
                'metadata' => $object->getAllMetadata(),
            ];
        }
        return $this->decorated->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return $this->decorated->supportsDenormalization($data, $type, $format);
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param string|null $format
     * @param array $context
     * @return mixed
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        return $this->decorated->denormalize($data, $class, $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }
}
