<?php

namespace App\Enums;

enum Uri: string
{
    case card_set = 'card_set';
    case card_set_id = 'card_set/{card_set}';
    case card_set_cards = 'card_set/{card_set}/cards';

    case card = 'card';
    case card_id = 'card/{card}';

    case test = 'test';
    case test_id = 'test/{test}';
    case test_tasks = 'test/{test}/tasks';

    case check_answers = 'test/{test}/answers';

    case task = 'task';
    case task_id = 'task/{task}';

    case section = 'section';
    case section_id = 'section/{section}';

    case user = 'user';
    case user_id = 'user/{user}';

    case children = 'children';
    case children_id = 'children/{child}';
    case children_password = 'children/{child}/password';

    case register = 'register';
    case login = 'login';
    case me = 'me';

    case classification = 'filters/classification';
    case difficulty = 'filters/difficulty';

    case files = 'files';
}
