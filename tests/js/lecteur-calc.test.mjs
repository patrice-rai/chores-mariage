// Fonctions pures du lecteur (public/js/lecteur-calc.js) : cadence du décompte, boucle, formats.
import assert from 'node:assert/strict'
import { test } from 'node:test'
import {
  cadenceMs,
  dansBoucle,
  formatDuree,
  formatTemps,
  libelleVitesse,
  pourcentage,
  sectionA,
  sectionPrecedente,
  sectionSuivante,
  statutRepetition,
} from '../../public/js/lecteur-calc.js'

test('cadence : 140 temps par minute à 0,5 × = 857 ms', () => {
  assert.equal(cadenceMs(140, 0.5), 857)
})

test('cadence : 140 à 1 × = 429 ms, 120 à 0,75 × = 667 ms', () => {
  assert.equal(cadenceMs(140, 1), 429)
  assert.equal(cadenceMs(120, 0.75), 667)
})

test('cadence : plancher de 380 ms', () => {
  assert.equal(cadenceMs(200, 1), 380)
  assert.equal(cadenceMs(400, 1), 380)
})

test('cadence : tempo inconnu → 120 temps par minute', () => {
  assert.equal(cadenceMs(null, 1), 500)
  assert.equal(cadenceMs(0, 1), 500)
  assert.equal(cadenceMs(Number.NaN, 0.5), 1000)
})

test('boucle : dans la section, on ne bouge pas', () => {
  assert.equal(dansBoucle(3, 0, 8), 3)
  assert.equal(dansBoucle(8.5, 8, 16), 8.5)
})

test('boucle : à la fin (marge de 0,08 s), retour au début', () => {
  assert.equal(dansBoucle(7.95, 0, 8), 0)
  assert.equal(dansBoucle(8, 0, 8), 0)
  assert.equal(dansBoucle(16.4, 8, 16), 8)
})

test('boucle : nettement avant le début, retour au début ; un peu avant, toléré', () => {
  assert.equal(dansBoucle(2, 8, 16), 8)
  assert.equal(dansBoucle(7.9, 8, 16), 7.9)
})

test('formats : 0:17, 4 min 04, 1:02:05, 0,75 ×', () => {
  assert.equal(formatTemps(17.4), '0:17')
  assert.equal(formatTemps(17.9), '0:17')
  assert.equal(formatTemps(0), '0:00')
  assert.equal(formatTemps(-3), '0:00')
  assert.equal(formatTemps(3725), '1:02:05')
  assert.equal(formatDuree(244), '4 min 04')
  assert.equal(formatDuree(20), '0 min 20')
  assert.equal(formatDuree(null), 'durée inconnue')
  assert.equal(libelleVitesse(0.75), '0,75 ×')
  assert.equal(libelleVitesse(1), '1 ×')
})

test('sections : suivante et précédente bouclent, rien sans section', () => {
  assert.equal(sectionSuivante(0, 3), 1)
  assert.equal(sectionSuivante(2, 3), 0)
  assert.equal(sectionPrecedente(0, 3), 2)
  assert.equal(sectionSuivante(0, 0), null)
  assert.equal(sectionPrecedente(0, 0), null)
})

test('sections : section qui contient un instant', () => {
  const sections = [
    { debut: 0, fin: 8 },
    { debut: 8, fin: 16 },
  ]
  assert.equal(sectionA(0, sections), 0)
  assert.equal(sectionA(8, sections), 1)
  assert.equal(sectionA(17, sections), -1)
})

test('progression et statut du mode répétition', () => {
  assert.equal(pourcentage(5, 20), 25)
  assert.equal(pourcentage(30, 20), 100)
  assert.equal(pourcentage(5, 0), 0)
  assert.equal(statutRepetition(true, 0.75, true), 'Miroir · 0,75 × · Boucle')
  assert.equal(statutRepetition(false, 1, false), 'Pas de miroir · 1 ×')
})
