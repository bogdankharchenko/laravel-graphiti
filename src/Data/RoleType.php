<?php

namespace BogdanKharchenko\Graphiti\Data;

enum RoleType: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';
}
