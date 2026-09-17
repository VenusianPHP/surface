<?php

namespace Surface\Contracts\HumanInput;

/** Physical keys, layout-independent. Engines translate their scancode / keyCode / hardware keycode into these; an unmapped key is UNKNOWN, never an exception. */
enum Key: string
{
    case A = 'a';
    case B = 'b';
    case C = 'c';
    case D = 'd';
    case E = 'e';
    case F = 'f';
    case G = 'g';
    case H = 'h';
    case I = 'i';
    case J = 'j';
    case K = 'k';
    case L = 'l';
    case M = 'm';
    case N = 'n';
    case O = 'o';
    case P = 'p';
    case Q = 'q';
    case R = 'r';
    case S = 's';
    case T = 't';
    case U = 'u';
    case V = 'v';
    case W = 'w';
    case X = 'x';
    case Y = 'y';
    case Z = 'z';

    case DIGIT_0 = 'digit_0';
    case DIGIT_1 = 'digit_1';
    case DIGIT_2 = 'digit_2';
    case DIGIT_3 = 'digit_3';
    case DIGIT_4 = 'digit_4';
    case DIGIT_5 = 'digit_5';
    case DIGIT_6 = 'digit_6';
    case DIGIT_7 = 'digit_7';
    case DIGIT_8 = 'digit_8';
    case DIGIT_9 = 'digit_9';

    case F1 = 'f1';
    case F2 = 'f2';
    case F3 = 'f3';
    case F4 = 'f4';
    case F5 = 'f5';
    case F6 = 'f6';
    case F7 = 'f7';
    case F8 = 'f8';
    case F9 = 'f9';
    case F10 = 'f10';
    case F11 = 'f11';
    case F12 = 'f12';

    case UP = 'up';
    case DOWN = 'down';
    case LEFT = 'left';
    case RIGHT = 'right';

    case SPACE = 'space';
    case ENTER = 'enter';
    case ESCAPE = 'escape';
    case TAB = 'tab';
    case BACKSPACE = 'backspace';
    case DELETE = 'delete';
    case INSERT = 'insert';
    case HOME = 'home';
    case END = 'end';
    case PAGE_UP = 'page_up';
    case PAGE_DOWN = 'page_down';
    case CAPS_LOCK = 'caps_lock';

    case LEFT_SHIFT = 'left_shift';
    case RIGHT_SHIFT = 'right_shift';
    case LEFT_CTRL = 'left_ctrl';
    case RIGHT_CTRL = 'right_ctrl';
    case LEFT_ALT = 'left_alt';
    case RIGHT_ALT = 'right_alt';
    case LEFT_META = 'left_meta';
    case RIGHT_META = 'right_meta';

    case MINUS = 'minus';
    case EQUALS = 'equals';
    case LEFT_BRACKET = 'left_bracket';
    case RIGHT_BRACKET = 'right_bracket';
    case BACKSLASH = 'backslash';
    case SEMICOLON = 'semicolon';
    case APOSTROPHE = 'apostrophe';
    case GRAVE = 'grave';
    case COMMA = 'comma';
    case PERIOD = 'period';
    case SLASH = 'slash';

    case NUMPAD_0 = 'numpad_0';
    case NUMPAD_1 = 'numpad_1';
    case NUMPAD_2 = 'numpad_2';
    case NUMPAD_3 = 'numpad_3';
    case NUMPAD_4 = 'numpad_4';
    case NUMPAD_5 = 'numpad_5';
    case NUMPAD_6 = 'numpad_6';
    case NUMPAD_7 = 'numpad_7';
    case NUMPAD_8 = 'numpad_8';
    case NUMPAD_9 = 'numpad_9';

    case NUMPAD_DIVIDE = 'numpad_divide';
    case NUMPAD_MULTIPLY = 'numpad_multiply';
    case NUMPAD_MINUS = 'numpad_minus';
    case NUMPAD_PLUS = 'numpad_plus';
    case NUMPAD_ENTER = 'numpad_enter';
    case NUMPAD_PERIOD = 'numpad_period';
    case NUMPAD_EQUALS = 'numpad_equals';

    case PRINT_SCREEN = 'print_screen';
    case SCROLL_LOCK = 'scroll_lock';
    case PAUSE = 'pause';
    case NUM_LOCK = 'num_lock';
    case MENU = 'menu';
    case FUNCTION = 'function';
    case CLEAR = 'clear';

    case UNKNOWN = 'unknown';
}
