import { lanes } from "./site-lanes";

export const siteOrigin = "https://www.creditsoft.app";

export const staticPublicRoutes = [
  "/",
  "/features",
  "/videos",
  "/pricing-plan",
  "/contact-us",
  "/about-us",
  "/docs",
  "/resources",
  "/downloads",
  "/releases",
  "/renewal",
];

export const publicRoutes = Array.from(
  new Set([...staticPublicRoutes, ...lanes.map((lane) => `/${lane.slug}`)]),
).sort((left, right) => left.localeCompare(right));

export const publicUrls = publicRoutes.map((path) => new URL(path, siteOrigin).toString());
