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
    'unavailable_ask_contact' => 'Ara mateix :owner no està disponible. Deixa\'m el teu correu o el teu telèfon i què necessites, i et respondrà personalment tan aviat com pugui.',
    'keep_asking' => 'Perquè :owner et pugui respondre, deixa\'m el teu correu o el teu telèfon.',
    'ask_contact' => 'Si vols, deixa\'m el teu nom i un correu o telèfon i :owner t\'escriurà. Només els fem servir per respondre\'t.',
    'ask_contact_privacy' => 'Si vols, deixa\'m el teu nom i un correu o telèfon i :owner t\'escriurà. Només els fem servir per respondre\'t: :privacy',
    'contact_thanks' => 'Gràcies, ja tinc les teves dades. :owner t\'escriurà personalment tan aviat com pugui.',

    // Mode changes and endings.
    'hello_again' => 'Hola de nou! Soc :bot, en què et puc ajudar?',
    'back_to_ai' => ':owner està ocupat en aquest moment, però jo segueixo aquí per ajudar-te. En què et puc ajudar?',
    'closed' => 'Aquesta conversa ha finalitzat. Si necessites alguna cosa de :site, escriu-nos a :email.',
    'closed_no_email' => 'Aquesta conversa ha finalitzat.',
    'session_limit' => 'Hem arribat al límit de missatges d\'aquesta conversa. Escriu-nos a :email i et respondrem personalment.',
    'session_limit_no_email' => 'Hem arribat al límit de missatges d\'aquesta conversa.',
];
