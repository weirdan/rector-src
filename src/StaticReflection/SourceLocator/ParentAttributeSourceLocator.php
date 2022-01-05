<?php

declare(strict_types=1);

namespace Rector\Core\StaticReflection\SourceLocator;

use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Namespace_;
use PHPStan\BetterReflection\Identifier\Identifier;
use PHPStan\BetterReflection\Identifier\IdentifierType;
use PHPStan\BetterReflection\Reflection\Reflection;
use PHPStan\BetterReflection\Reflection\ReflectionClass;
use PHPStan\BetterReflection\Reflector\ClassReflector;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\BetterReflection\SourceLocator\Located\LocatedSource;
use PHPStan\BetterReflection\SourceLocator\Type\SourceLocator;
use PHPStan\Reflection\BetterReflection\Reflector\MemoizingReflector;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Core\PhpParser\AstResolver;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * This mimics classes that PHPStan fails to find in scope, but actually has an access in static reflection.
 * Some weird bug, that crashes on parent classes.
 *
 * @see https://github.com/rectorphp/rector-src/pull/368/
 */
final class ParentAttributeSourceLocator implements SourceLocator
{
    private AstResolver $astResolver;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    #[Required]
    public function autowire(AstResolver $astResolver): void
    {
        $this->astResolver = $astResolver;
    }

    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
    {
        $identifierName = $identifier->getName();
        if ($identifierName === 'Symfony\Component\DependencyInjection\Attribute\Autoconfigure' && $this->reflectionProvider->hasClass(
            $identifierName
        )) {
            $classReflection = $this->reflectionProvider->getClass($identifierName);

            $class = $this->astResolver->resolveClassFromClassReflection($classReflection, $identifierName);
            if ($class === null) {
                return null;
            }

            $class->namespacedName = new FullyQualified($identifierName);
            $fakeLocatedSource = new LocatedSource('virtual', null);
            $memoizingReflector = new MemoizingReflector($this);

            return ReflectionClass::createFromNode(
                $memoizingReflector,
                $class,
                $fakeLocatedSource,
                new Namespace_(new Name('Symfony\Component\DependencyInjection\Attribute'))
            );
        }

        return null;
    }

    /**
     * @return array<int, Reflection>
     */
    public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
    {
        return [];
    }
}
