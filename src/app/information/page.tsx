export default function InformationPage() {
  return (
    <section className="section">
      <h1 className="section-title">Informação útil</h1>
      <p className="section-lead">
        O Reporta Évora permite aos cidadãos comunicar problemas no espaço
        público e nas vias, com fotografia e localização, para que os serviços
        municipais possam intervir.
      </p>
      <div className="feature-grid">
        <article className="feature-item">
          <h3>Ocorrências urbanas</h3>
          <p>Pavimentos, mobiliário urbano, espaços verdes e outros problemas.</p>
        </article>
        <article className="feature-item">
          <h3>Ocorrências de estrada</h3>
          <p>Buracos, sinalização e situações que afetam a circulação.</p>
        </article>
        <article className="feature-item">
          <h3>Acompanhamento</h3>
          <p>Consulte o mapa e a sua área pessoal para ver o estado dos pedidos.</p>
        </article>
      </div>
    </section>
  );
}
