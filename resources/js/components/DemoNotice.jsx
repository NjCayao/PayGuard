import { alertErrorClass } from './ui';
import { DEMO_BLOCKED_MESSAGE } from '../utils/demo';

export default function DemoNotice() {
    return <p className={`${alertErrorClass} mt-4`}>{DEMO_BLOCKED_MESSAGE}</p>;
}
