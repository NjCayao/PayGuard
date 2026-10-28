import { Link } from 'react-router-dom';
import { formatDate, formatTime } from '../utils/format';

export default function DateCell({ value, timeZone, to }) {
    const content = (
        <span className="block whitespace-nowrap">
            {formatDate(value, timeZone)}
            <span className="block text-xs font-normal text-slate-500">
                {formatTime(value, timeZone)}
            </span>
        </span>
    );

    if (to) {
        return (
            <Link to={to} className="font-semibold text-emerald-800 hover:underline">
                {content}
            </Link>
        );
    }

    return content;
}
