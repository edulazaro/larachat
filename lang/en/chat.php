<?php

return [
    // Refusals the guards send without calling the model.
    'code' => 'I don\'t write or review code here, I\'m :site\'s assistant. What else can I help you with?',
    'injection' => 'I can\'t do that. I\'m here to help with anything about :site. What can I do for you?',
    'blocked' => 'In this conversation I can only help with questions about :site. If you need anything, email us at :email and we\'ll reply personally.',
    'blocked_no_email' => 'In this conversation I can only help with questions about :site.',
    'limit' => 'We\'ve talked a lot here today. If you\'d like to continue, email us at :email and we\'ll reply personally.',
    'limit_no_email' => 'We\'ve talked a lot here today. Come back tomorrow and we\'ll carry on.',
    'noise' => 'I didn\'t catch that. Tell me what you need and I\'ll help.',
    'offtopic' => 'I can only help here with anything about :site. What do you need?',
    'offtopic_blocked' => 'This chat is only for questions about :site, so I\'ll leave it here for today. If you need anything, email us at :email and we\'ll reply personally.',
    'offtopic_blocked_no_email' => 'This chat is only for questions about :site, so I\'ll leave it here for today.',

    // When the visitor asks for a person and nobody is at the panel.
    'unavailable_with_contact' => ':owner isn\'t available right now, but I have your details and they\'ll get back to you personally as soon as possible.',
    'unavailable_ask_contact' => ':owner isn\'t available right now. Leave your details below and they\'ll get back to you personally as soon as possible.',

    // Mode changes and endings.
    'hello_again' => 'Hello again! I\'m :bot, how can I help you?',
    'back_to_ai' => ':owner is busy right now, but I\'m still here to help. What can I do for you?',
    'closed' => 'This conversation has ended. If you need anything from :site, please email us at :email.',
    'closed_no_email' => 'This conversation has ended.',
    'session_limit' => 'This conversation has reached its message limit. Please email us at :email and we\'ll reply personally.',
    'session_limit_no_email' => 'This conversation has reached its message limit.',

    // The contact form: its title for each moment it appears, the privacy line and the errors.
    'form_interest' => 'So :owner can get back to you',
    'form_price' => 'So :owner can send you a quote',
    'form_goodbye' => 'Before you go: where can :owner reach you?',
    'form_close' => 'Would you like :owner to get in touch?',
    'form_handoff' => 'Leave your details for :owner',
    'form_wait' => ':owner hasn\'t been able to answer yet: leave your details',
    'form_privacy' => 'We only use them to reply to you.',
    'form_privacy_link' => 'Privacy policy',
    'form_name_required' => 'Please enter your name.',
    'form_name_invalid' => 'Use letters only in the name.',
    'form_email_required' => 'Please enter your email.',
    'form_email_invalid' => 'That email doesn\'t look right.',
    'form_phone_invalid' => 'That phone number doesn\'t look right.',

    // What the visitor's details read like in the conversation, and the thanks.
    'contact_message' => 'Here are my details: :details',
    'contact_thanks' => 'Thanks, :name. :owner will write to you at :email as soon as possible.',
    'contact_thanks_unnamed' => 'Thanks. :owner will write to you at :email as soon as possible.',
    'contact_thanks_phone' => 'Thanks, :name. :owner will call you at :phone as soon as possible.',
    'contact_thanks_phone_unnamed' => 'Thanks. :owner will call you at :phone as soon as possible.',
];
