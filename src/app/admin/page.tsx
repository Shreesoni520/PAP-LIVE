export default function AdminHomePage() {
  return (
    <section className="section" style={{ paddingTop: "8rem" }}>
      <div className="container" style={{ maxWidth: 720 }}>
        <div className="section-title">
          <h2>Painel de Administração</h2>
          <p>
            O painel admin original (Mazer) será restaurado aqui página a
            página — login, dashboard, mapa, ocorrências, árvores, notícias e
            utilizadores — sem alterar o design que já tinha.
          </p>
        </div>
        <p className="text-muted">
          Entretanto use localmente o painel PHP em{" "}
          <code>/Admin/</code> se ainda precisar, enquanto migrámos o resto.
        </p>
        <a href="/admin/login" className="btn btn-primary rounded-pill px-3">
          Ir para login admin
        </a>
      </div>
    </section>
  );
}
