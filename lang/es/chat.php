<?php

return [
    // Refusals the guards send without calling the model.
    'code' => 'Por aquí no escribo ni reviso código, soy el asistente de :site. ¿En qué más te puedo ayudar?',
    'injection' => 'Eso no lo puedo hacer. Estoy aquí para ayudarte con lo relacionado con :site. ¿En qué te puedo ayudar?',
    'blocked' => 'En esta conversación solo puedo atender consultas sobre :site. Si necesitas algo, escríbenos a :email y te respondemos personalmente.',
    'blocked_no_email' => 'En esta conversación solo puedo atender consultas sobre :site.',
    'limit' => 'Hoy ya hemos hablado mucho por aquí. Si quieres seguir, escríbenos a :email y te respondemos personalmente.',
    'limit_no_email' => 'Hoy ya hemos hablado mucho por aquí. Vuelve mañana y seguimos.',
    // The day's reply budget is spent: no model until midnight, the form instead.
    'busy' => 'Ahora mismo no puedo responderte con detalle. Déjale tus datos a :owner aquí abajo y te escribirá personalmente.',
    'noise' => 'No he entendido eso. Dime qué necesitas y te ayudo.',
    'offtopic' => 'Aquí solo te puedo ayudar con lo relacionado con :site. ¿Qué necesitas?',
    'offtopic_blocked' => 'Este chat es solo para consultas sobre :site, así que por hoy lo dejo aquí. Si necesitas algo, escríbenos a :email y te respondemos personalmente.',
    'offtopic_blocked_no_email' => 'Este chat es solo para consultas sobre :site, así que por hoy lo dejo aquí.',

    // When the visitor asks for a person and nobody is at the panel.
    'unavailable_with_contact' => 'Ahora mismo :owner no está disponible, pero ya tengo tus datos y te escribirá personalmente lo antes posible.',
    'unavailable_ask_contact' => 'Ahora mismo :owner no está disponible. Déjale tus datos aquí abajo y te escribirá personalmente lo antes posible.',
    'unavailable_form_open' => 'Tienes el formulario justo aquí abajo: déjale tus datos y :owner te escribirá personalmente lo antes posible.',

    // Mode changes and endings.
    'hello_again' => '¡Hola de nuevo! Soy :bot, ¿en qué te puedo ayudar?',
    'back_to_ai' => ':owner está ocupado en este momento, pero yo sigo aquí para ayudarte. ¿En qué te puedo echar una mano?',
    'closed' => 'Esta conversación ha finalizado. Si necesitas algo de :site, escríbenos a :email.',
    'closed_no_email' => 'Esta conversación ha finalizado.',
    'session_limit' => 'Hemos llegado al límite de mensajes de esta conversación. Escríbenos a :email y te respondemos personalmente.',
    'session_limit_no_email' => 'Hemos llegado al límite de mensajes de esta conversación.',

    // The contact form: its title for each moment it appears, the privacy line and the errors.
    'form_interest' => 'Para que :owner te escriba',
    'form_contact_request' => 'Para que :owner te contacte',
    'form_price' => 'Para que :owner te envíe el presupuesto',
    'form_goodbye' => 'Antes de irte: ¿dónde te escribe :owner?',
    'form_close' => '¿Quieres que :owner te escriba?',
    'form_handoff' => 'Déjale tus datos a :owner',
    'form_wait' => ':owner no ha podido contestar todavía: déjale tus datos',
    'form_busy' => 'Para que :owner te escriba',
    'form_privacy' => 'Solo los usamos para responderte.',
    'form_privacy_link' => 'Privacidad',
    'form_name_required' => 'Escribe tu nombre.',
    'form_name_invalid' => 'Escribe solo letras en el nombre.',
    'form_email_required' => 'Escribe tu email.',
    'form_email_invalid' => 'Ese email no parece correcto.',
    'form_phone_invalid' => 'Ese teléfono no parece correcto.',

    // What the visitor's details read like in the conversation, and the thanks.
    'contact_message' => 'Estos son mis datos: :details',
    'contact_thanks' => 'Gracias, :name. :owner te escribirá a :email lo antes posible.',
    'contact_thanks_unnamed' => 'Gracias. :owner te escribirá a :email lo antes posible.',
    'contact_thanks_phone' => 'Gracias, :name. :owner te llamará al :phone lo antes posible.',
    'contact_thanks_phone_unnamed' => 'Gracias. :owner te llamará al :phone lo antes posible.',
];
