<?php

return [
  "TwingleProject" => [
    "project_data" => [
      "id",
      "identifier",
      "allow_more",
      "name",
      "organisation_id",
      "project_target",
      "transaction_type",
      "type",
      "last_update",
      "url"
    ],
    "project_embed_data" => [
      "page",
      "widget",
      "form",
      "form-single",
      "widget-single",
      "eventall",
      "eventlist",
      "counter"
    ],
    "project_options" => [
      "has_confirmation_mail",
      "has_donation_receipt",
      "has_contact_data",
      "donation_rhythm",
      "default_rhythm",
      "has_newsletter_registration",
      "has_postinfo_registration",
      "design_background_color",
      "design_primary_color",
      "design_font_color",
      "bcc_email_address",
      "donation_value_min",
      "donation_value_max",
      "donation_value_default",
      "contact_fields",
      "exclude_contact_fields",
      "mandatory_contact_fields",
      "custom_css",
      "share_url",
      "has_contact_mandatory",
      "has_doi",
      "has_force_donation_target_buttons",
      "slidericon",
      "has_hidden_logo",
      "has_projecttarget_as_money",
      "has_donationtarget_textfield",
      "has_civi_crm_activated",
      "has_step_index",
      "languages",
      "has_buttons",
      "has_no_slider",
      "buttons",
      "has_newsletter_namerequest",
      "has_show_donator_data"
    ],
    "payment_methods" => [
      "has_paypal",
      "has_banktransfer",
      "has_debit",
      "has_sofortueberweisung",
      "has_paypal_recurring",
      "has_debit_recurring"
    ]
  ],
  "TwingleEvent" => [
    "event_data" => [
      "id",
      "project_id",
      "identifier",
      "description",
      "user_name",
      "user_email",
      "is_public",
      "deleted",
      "confirmed_at",
      "created_at",
      "updated_at",
      "target",
      "creation_url",
      "show_internal",
      "show_external",
      "edit_internal",
      "edit_external",
      "urls"
    ],
    "event_embed_data" => [
      "page",
      "eventpage",
      "widget",
      "form",
      "widget-single",
      "form-single",
      "eventall",
      "eventlist",
      "eventeditcreate"
    ]
  ],
  "TwingleCampaign" => [
    "campaign_data" => [
      "id",
      "parent_id",
      "name",
      "title"
    ]
  ]
];

