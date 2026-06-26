<div class="widget box box-no-shadow">
    <div class="widget-header">
        <h4>NewsMAN</h4>
    </div>
    <div class="widget-content">
        <p>Extension installed and enabled.</p>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <th>Remarketing enabled</th>
                    <td>{if $enabled}Yes{else}No{/if}</td>
                </tr>
                <tr>
                    <th>Remarketing ID</th>
                    <td>{$remarketingId|escape}</td>
                </tr>
                <tr>
                    <th>Tracking script</th>
                    <td>{$trackingUrl|escape}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

