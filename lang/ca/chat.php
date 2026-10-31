<?php

return [
    // Refusals the guards send without calling the model.
    'code' => 'Per aquí no escric ni reviso codi, soc l\'assistent de :site. En què més et puc ajudar?',
    'injection' => 'Això no ho puc fer. Soc aquí per ajudar-te amb tot el que té a veure amb :site. En què et puc ajudar?',
    'blocked' => 'En aquesta conversa només puc atendre consultes sobre :site. Si necessites alguna cosa, escriu-nos a :email i et respondrem personalment.',
    'blocked_no_email' => 'En aquesta conversa només puc atendre consultes sobre :site.',
    'limit' => 'Avui ja hem parlat molt per aquí. Si vols continuar, escriu-nos a :email i et respondrem personalment.',
    'limit_no_email' => 'Avui ja hem parlat molt per aquí. Torna demà i continuem.',
    'noise' => 'No ho he entès. Digues-me què necessites i t\'ajudo.',
    'offtopic' => 'Aquí només et puc ajudar amb el que té a veure amb :site. Què necessites?',
    'offtopic_blocked' => 'Aquest xat és només per a consultes sobre :site, així que per avui ho deixo aquí. Si necessites alguna cosa, escriu-nos a :email i et respondrem personalment.',
    'offtopic_blocked_no_email' => 'Aquest xat és només per a consultes sobre :site, així que per avui ho deixo aquí.',

    // When the visitor asks for a person and nobody is at the panel.
    'unavailable_with_contact' => 'Ara mateix :owner no està disponible, però ja tinc les teves dades i t\'escriurà personalment tan aviat com pugui.',
    'unavailable_ask_contact' => 'Ara mateix :owner no està disponible. Deixa-li les teves dades aquí sota i t\'escriurà personalment tan aviat com pugui.',

    // Mode changes and endings.
    'hello_again' => 'Hola de nou! Soc :bot, en què et puc ajudar?',
    'back_to_ai' => ':owner està ocupat en aquest moment, però jo segueixo aquí per ajudar-te. En què et puc ajudar?',
    'closed' => 'Aquesta conversa ha finalitzat. Si necessites alguna cosa de :site, escriu-nos a :email.',
    'closed_no_email' => 'Aquesta conversa ha finalitzat.',
    'session_limit' => 'Hem arribat al límit de missatges d\'aquesta conversa. Escriu-nos a :email i et respondrem personalment.',
    'session_limit_no_email' => 'Hem arribat al límit de missatges d\'aquesta conversa.',

    // The contact form: its title for each moment it appears, the privacy line and the errors.
    'form_interest' => 'Perquè :owner t\'escrigui',
    'form_price' => 'Perquè :owner t\'enviï el pressupost',
    'form_goodbye' => 'Abans de marxar: on t\'escriu :owner?',
    'form_close' => 'Vols que :owner t\'escrigui?',
    'form_handoff' => 'Deixa les teves dades a :owner',
    'form_wait' => ':owner encara no ha pogut respondre: deixa-li les teves dades',
    'form_privacy' => 'Només les fem servir per respondre\'t.',
    'form_privacy_link' => 'Privacitat',
    'form_name_required' => 'Escriu el teu nom.',
    'form_name_invalid' => 'Escriu només lletres al nom.',
    'form_email_required' => 'Escriu el teu correu.',
    'form_email_invalid' => 'Aquest correu no sembla correcte.',
    'form_phone_invalid' => 'Aquest telèfon no sembla correcte.',

    // What the visitor's details read like in the conversation, and the thanks.
    'contact_message' => 'Aquestes són les meves dades: :details',
    'contact_thanks' => 'Gràcies, :name. :owner t\'escriurà a :email tan aviat com pugui.',
    'contact_thanks_unnamed' => 'Gràcies. :owner t\'escriurà a :email tan aviat com pugui.',
    'contact_thanks_phone' => 'Gràcies, :name. :owner et trucarà al :phone tan aviat com pugui.',
    'contact_thanks_phone_unnamed' => 'Gràcies. :owner et trucarà al :phone tan aviat com pugui.',
];
