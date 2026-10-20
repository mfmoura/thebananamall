export default function TabelaSkeleton({ colunas, linhas = 10, rotulo = 'registros' }) {
    return <div className="dashboard-tabela tabela-skeleton" aria-busy="true">
        <p className="sr-only" role="status">Carregando {rotulo}…</p>
        <table aria-hidden="true">
            <caption>Carregando {rotulo}…</caption>
            <thead><tr>{colunas.map((coluna) => <th key={coluna}>{coluna}</th>)}</tr></thead>
            <tbody>{Array.from({ length: linhas }, (_, linha) => <tr key={linha}>
                {colunas.map((coluna) => <td key={coluna}><span className="skeleton-bloco tabela-skeleton-celula" /></td>)}
            </tr>)}</tbody>
        </table>
    </div>;
}
