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
    'unavailable_ask_contact' => ':owner n\'est pas disponible pour le moment. Laissez-moi votre e-mail ou votre téléphone et ce dont vous avez besoin, et vous recevrez une réponse personnelle dès que possible.',
    'keep_asking' => 'Pour que :owner puisse vous répondre, laissez-moi votre e-mail ou votre téléphone.',
    'ask_contact' => 'Si vous le souhaitez, laissez-moi votre nom et un e-mail ou un téléphone, et :owner vous écrira. Nous ne les utilisons que pour vous répondre.',
    'ask_contact_privacy' => 'Si vous le souhaitez, laissez-moi votre nom et un e-mail ou un téléphone, et :owner vous écrira. Nous ne les utilisons que pour vous répondre : :privacy',
    'contact_thanks' => 'Merci, j\'ai vos coordonnées. :owner vous écrira personnellement dès que possible.',

    // Mode changes and endings.
    'hello_again' => 'Re-bonjour ! Je suis :bot, comment puis-je vous aider ?',
    'back_to_ai' => ':owner est occupé pour le moment, mais je suis toujours là pour vous aider. Que puis-je faire pour vous ?',
    'closed' => 'Cette conversation est terminée. Si vous avez besoin de quelque chose de :site, écrivez-nous à :email.',
    'closed_no_email' => 'Cette conversation est terminée.',
    'session_limit' => 'Cette conversation a atteint sa limite de messages. Écrivez-nous à :email et nous vous répondrons personnellement.',
    'session_limit_no_email' => 'Cette conversation a atteint sa limite de messages.',
];
