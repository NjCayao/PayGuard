export default function Bilingual({ es, en }) {
    return (
        <span className="block">
            <span className="block">{es}</span>
            <span className="block text-xs font-normal text-slate-500">{en}</span>
        </span>
    );
}
