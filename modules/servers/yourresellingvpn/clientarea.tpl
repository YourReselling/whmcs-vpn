{* Overview, product views and management actions follow the supplied VPS/TeamSpeak/Plesk templates. *}
<section class="panel panel-default card mb-4" aria-label="{$yrTitle|escape:'html'}">
    <div class="panel-heading card-header"><h3 class="panel-title mb-0">{$yrTitle|escape:'html'}</h3></div>
    <div class="panel-body card-body">
        {if $yrError}<div class="alert alert-danger" role="alert">{$yrError|escape:'html'}</div>{/if}
        {if $yrNotice}<div class="alert alert-success" role="status">{$yrNotice|escape:'html'}</div>{/if}
        {if $yrSecrets}<div class="alert alert-info"><h4>Zugangsdaten</h4><dl>{foreach $yrSecrets as $secret}<dt>{$secret.label|escape:'html'}</dt><dd><pre style="white-space:pre-wrap">{$secret.value|escape:'html'}</pre></dd>{/foreach}</dl></div>{/if}
        <nav class="mb-3" aria-label="Produktverwaltung">
            <a class="btn btn-default btn-sm mb-2" href="clientarea.php?action=productdetails&amp;id={$yrServiceId|escape:'html'}">Übersicht</a>
            {foreach $yrViews as $view}
                <a class="btn btn-default btn-sm mb-2" href="clientarea.php?action=productdetails&amp;id={$yrServiceId|escape:'html'}&amp;yr_view={$view.key|escape:'url'}">{$view.label|escape:'html'}</a>
            {/foreach}
        </nav>
        {if $yrRows}
            <div class="table-responsive"><table class="table table-striped table-sm"><tbody>
                {foreach $yrRows as $row}<tr><th scope="row">{$row.label|escape:'html'}</th><td style="overflow-wrap:anywhere;white-space:pre-wrap">{$row.value|escape:'html'}</td></tr>{/foreach}
            </tbody></table></div>
        {/if}
        {foreach $yrControls as $control}
            <details class="panel panel-default card mb-3">
                <summary class="panel-heading card-header">{$control.label|escape:'html'}</summary>
                <form method="post" action="clientarea.php?action=productdetails&amp;id={$yrServiceId|escape:'html'}" class="panel-body card-body">
                    <input type="hidden" name="token" value="{$token|escape:'html'}">
                    <input type="hidden" name="yr_action" value="{$control.key|escape:'html'}">
                    {foreach $control.fields as $field}
                        <div class="form-group mb-3">
                            <label for="yr-{$control.key|escape:'html'}-{$field.name|escape:'html'}">{$field.label|escape:'html'}{if $field.required} *{/if}</label>
                            {if $field.options || $field.type == 'boolean'}
                                <select class="form-control" id="yr-{$control.key|escape:'html'}-{$field.name|escape:'html'}" name="yr_fields[{$field.name|escape:'html'}]" {if $field.required}required{/if}>
                                    <option value="">Bitte wählen</option>
                                    {if $field.type == 'boolean'}<option value="true">Ja</option><option value="false">Nein</option>{else}
                                    {foreach $field.options as $option}<option value="{$option|escape:'html'}">{$option|escape:'html'}</option>{/foreach}{/if}
                                </select>
                            {else}
                                <input class="form-control" id="yr-{$control.key|escape:'html'}-{$field.name|escape:'html'}" name="yr_fields[{$field.name|escape:'html'}]" type="{if $field.type == 'integer' || $field.type == 'number'}number{else}text{/if}" {if $field.required}required{/if}>
                            {/if}
                        </div>
                    {/foreach}
                    <label class="d-block"><input type="checkbox" name="yr_confirm" value="1" required> Ich möchte diese Aktion ausführen.</label>
                    <button class="btn btn-primary" type="submit">{$control.label|escape:'html'}</button>
                </form>
            </details>
        {/foreach}
    </div>
</section>
