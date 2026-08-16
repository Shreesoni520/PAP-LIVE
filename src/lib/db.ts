import mysql, { Pool, PoolOptions, RowDataPacket, ResultSetHeader } from "mysql2/promise";

declare global {
  var __papDbPool: Pool | undefined;
}

function getPoolConfig(): PoolOptions {
  return {
    host: process.env.DB_HOST || "localhost",
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER || "root",
    password: process.env.DB_PASS || "",
    database: process.env.DB_NAME || "pap",
    waitForConnections: true,
    connectionLimit: 10,
    namedPlaceholders: true,
    timezone: "+01:00",
    charset: "utf8mb4",
  };
}

export function getDb(): Pool {
  if (!global.__papDbPool) {
    global.__papDbPool = mysql.createPool(getPoolConfig());
  }
  return global.__papDbPool;
}

export type { RowDataPacket, ResultSetHeader };
