<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\Contract;

/**
 * Implemented by the consuming application: the bundle has no notion of
 * what a "job offer" or any other business entity is, so this is the seam
 * through which it learns what to poll for.
 */
interface PendingDocumentProviderInterface
{
    /**
     * @return iterable<string> OpenSign document (contracts_Document) object ids currently awaiting signature.
     */
    public function getPendingDocumentIds(): iterable;
}
