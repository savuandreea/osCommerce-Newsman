{if $ext = \common\helpers\Acl::checkExtensionAllowed('NewsMAN', 'allowed')}
    {$ext::renderTrackingScript()}
    {$ext::renderFrontendEventsScript()}
    {$ext::renderPurchaseOnSuccessEvent()}
{/if}
