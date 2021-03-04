{* HEADER *}

<div>
    <h3>{ts domain="de.forumzfd.de"}General Settings{/ts}</h3>
    <div class="crm-section">
        <div class="label">{$form.twingle_api_key.label}</div>
        <div class="content">{$form.twingle_api_key.html}</div>
        <div class="clear"></div>
    </div>
</div>

<div>
    <h3>{ts domain="de.forumzfd.de"}Twingle Event Settings{/ts}</h3>
    <div class="crm-section">
        <div class="label">{$form.twinglecampaign_xcm_profile.label}</div>
        <div class="content">{$form.twinglecampaign_xcm_profile.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.twinglecampaign_start_case.label}</div>
        <div class="content">{$form.twinglecampaign_start_case.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.twinglecampaign_soft_credits.label}</div>
        <div class="content">{$form.twinglecampaign_soft_credits.html}</div>
        <div class="clear"></div>
    </div>
</div>


<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
