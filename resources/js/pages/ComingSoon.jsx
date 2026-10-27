import { cardClass } from '../components/ui';

export default function ComingSoon({ title }) {
    return (
        <div>
            <h1 className="text-xl font-bold text-slate-900">{title}</h1>
            <div className={`${cardClass} mt-4`}>
                <p className="text-slate-600">Próximamente.</p>
            </div>
        </div>
    );
}
