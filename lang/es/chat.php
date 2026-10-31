<?php

return [
    // Refusals the guards send without calling the model.
    'code' => 'Por aquí no escribo ni reviso código, soy el asistente de :site. ¿En qué más te puedo ayudar?',
    'injection' => 'Eso no lo puedo hacer. Estoy aquí para ayudarte con lo relacionado con :site. ¿En qué te puedo ayudar?',
    'blocked' => 'En esta conversación solo puedo atender consultas sobre :site. Si necesitas algo, escríbenos a :email y te respondemos personalmente.',
    'blocked_no_email' => 'En esta conversación solo puedo atender consultas sobre :site.',
    'limit' => 'Hoy ya hemos hablado mucho por aquí. Si quieres seguir, escríbenos a :email y te respondemos personalmente.',
    'limit_no_email' => 'Hoy ya hemos hablado mucho por aquí. Vuelve mañana y seguimos.',
    'noise' => 'No he entendido eso. Dime qué necesitas y te ayudo.',
    'offtopic' => 'Aquí solo te puedo ayudar con lo relacionado con :site. ¿Qué necesitas?',
    'offtopic_blocked' => 'Este chat es solo para consultas sobre :site, así que por hoy lo dejo aquí. Si necesitas algo, escríbenos a :email y te respondemos personalmente.',
    'offtopic_blocked_no_email' => 'Este chat es solo para consultas sobre :site, así que por hoy lo dejo aquí.',

    // When the visitor asks for a person and nobody is at the panel.
    'unavailable_with_contact' => 'Ahora mismo :owner no está disponible, pero ya tengo tus datos y te escribirá personalmente lo antes posible.',
    'unavailable_ask_contact' => 'Ahora mismo :owner no está disponible. Déjame tu email o tu teléfono y lo que necesitas, y te responde personalmente lo antes posible.',
    'keep_asking' => 'Para que :owner pueda responderte, déjame tu email o tu teléfono.',
    'ask_contact' => 'Si quieres, déjame tu nombre y un email o teléfono y :owner te escribe. Solo los usamos para responderte.',
    'ask_contact_privacy' => 'Si quieres, déjame tu nombre y un email o teléfono y :owner te escribe. Solo los usamos para responderte: :privacy',
    'contact_thanks' => 'Gracias, ya tengo tus datos. :owner te escribirá personalmente lo antes posible.',

    // Mode changes and endings.
    'hello_again' => '¡Hola de nuevo! Soy :bot, ¿en qué te puedo ayudar?',
    'back_to_ai' => ':owner está ocupado en este momento, pero yo sigo aquí para ayudarte. ¿En qué te puedo echar una mano?',
    'closed' => 'Esta conversación ha finalizado. Si necesitas algo de :site, escríbenos a :email.',
    'closed_no_email' => 'Esta conversación ha finalizado.',
    'session_limit' => 'Hemos llegado al límite de mensajes de esta conversación. Escríbenos a :email y te respondemos personalmente.',
    'session_limit_no_email' => 'Hemos llegado al límite de mensajes de esta conversación.',
];
