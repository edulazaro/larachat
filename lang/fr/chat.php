<?php

return [
    // Refusals the guards send without calling the model.
    'code' => 'Ici, je n\'écris ni ne relis de code : je suis l\'assistant de :site. En quoi puis-je vous aider d\'autre ?',
    'injection' => 'Je ne peux pas faire cela. Je suis là pour vous aider pour tout ce qui concerne :site. Que puis-je faire pour vous ?',
    'blocked' => 'Dans cette conversation, je ne peux répondre qu\'aux questions sur :site. Si vous avez besoin de quelque chose, écrivez-nous à :email et nous vous répondrons personnellement.',
    'blocked_no_email' => 'Dans cette conversation, je ne peux répondre qu\'aux questions sur :site.',
    'limit' => 'Nous avons déjà beaucoup échangé ici aujourd\'hui. Si vous souhaitez continuer, écrivez-nous à :email et nous vous répondrons personnellement.',
    'limit_no_email' => 'Nous avons déjà beaucoup échangé ici aujourd\'hui. Revenez demain et nous continuerons.',
    'noise' => 'Je n\'ai pas compris. Dites-moi ce dont vous avez besoin et je vous aide.',
    'offtopic' => 'Ici, je ne peux vous aider que pour ce qui concerne :site. De quoi avez-vous besoin ?',
    'offtopic_blocked' => 'Ce chat est réservé aux questions sur :site, je m\'arrête donc là pour aujourd\'hui. Si vous avez besoin de quelque chose, écrivez-nous à :email et nous vous répondrons personnellement.',
    'offtopic_blocked_no_email' => 'Ce chat est réservé aux questions sur :site, je m\'arrête donc là pour aujourd\'hui.',

    // When the visitor asks for a person and nobody is at the panel.
    'unavailable_with_contact' => ':owner n\'est pas disponible pour le moment, mais j\'ai vos coordonnées et vous recevrez une réponse personnelle dès que possible.',
    'unavailable_ask_contact' => ':owner n\'est pas disponible pour le moment. Laissez vos coordonnées ci-dessous et vous recevrez une réponse personnelle dès que possible.',

    // Mode changes and endings.
    'hello_again' => 'Re-bonjour ! Je suis :bot, comment puis-je vous aider ?',
    'back_to_ai' => ':owner est occupé pour le moment, mais je suis toujours là pour vous aider. Que puis-je faire pour vous ?',
    'closed' => 'Cette conversation est terminée. Si vous avez besoin de quelque chose de :site, écrivez-nous à :email.',
    'closed_no_email' => 'Cette conversation est terminée.',
    'session_limit' => 'Cette conversation a atteint sa limite de messages. Écrivez-nous à :email et nous vous répondrons personnellement.',
    'session_limit_no_email' => 'Cette conversation a atteint sa limite de messages.',

    // The contact form: its title for each moment it appears, the privacy line and the errors.
    'form_interest' => 'Pour que :owner vous écrive',
    'form_price' => 'Pour que :owner vous envoie un devis',
    'form_goodbye' => 'Avant de partir : où :owner peut vous écrire ?',
    'form_close' => 'Voulez-vous que :owner vous écrive ?',
    'form_handoff' => 'Laissez vos coordonnées à :owner',
    'form_wait' => ':owner n\'a pas encore pu répondre : laissez vos coordonnées',
    'form_privacy' => 'Nous ne les utilisons que pour vous répondre.',
    'form_privacy_link' => 'Confidentialité',
    'form_name_required' => 'Indiquez votre nom.',
    'form_name_invalid' => 'Utilisez uniquement des lettres dans le nom.',
    'form_email_required' => 'Indiquez votre e-mail.',
    'form_email_invalid' => 'Cet e-mail ne semble pas correct.',
    'form_phone_invalid' => 'Ce numéro de téléphone ne semble pas correct.',

    // What the visitor's details read like in the conversation, and the thanks.
    'contact_message' => 'Voici mes coordonnées : :details',
    'contact_thanks' => 'Merci, :name. :owner vous écrira à :email dès que possible.',
    'contact_thanks_unnamed' => 'Merci. :owner vous écrira à :email dès que possible.',
    'contact_thanks_phone' => 'Merci, :name. :owner vous appellera au :phone dès que possible.',
    'contact_thanks_phone_unnamed' => 'Merci. :owner vous appellera au :phone dès que possible.',
];
